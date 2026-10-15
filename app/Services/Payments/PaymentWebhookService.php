<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Services\Finance\ContributionService;

final class PaymentWebhookService
{
    public function __construct(
        private readonly Database $db,
        private readonly PaymentGatewayInterface $gateway,
        private readonly ContributionService $contributions
    ) {
    }

    /** @return array<string, mixed> */
    public function handle(string $rawPayload, ?string $signatureHeader, int $processedByUserId = 0): array
    {
        if (!$this->gateway->verifyWebhookSignature($rawPayload, $signatureHeader)) {
            throw new FinanceException('Invalid payment webhook signature.');
        }

        $event = $this->gateway->parseWebhookPayload($rawPayload);
        if ($event['event_id'] === '') {
            throw new FinanceException('Webhook event_id is required.');
        }

        $existing = $this->db->fetch(
            'SELECT id, processing_status FROM webhook_events
             WHERE provider = :provider AND event_id = :event_id LIMIT 1',
            ['provider' => $this->gateway->providerName(), 'event_id' => $event['event_id']]
        );
        if ($existing !== null) {
            return [
                'duplicate' => true,
                'event_id' => $event['event_id'],
                'status' => $existing['processing_status'],
            ];
        }

        $stokvelId = (int) $event['stokvel_id'];
        if ($stokvelId <= 0) {
            throw new FinanceException('Webhook missing stokvel_id.');
        }

        $eventRowId = (int) $this->db->insert(
            'INSERT INTO webhook_events
                (provider, event_id, event_type, stokvel_id, payload, signature_valid, processing_status, received_at, created_at)
             VALUES
                (:provider, :event_id, :event_type, :stokvel_id, :payload, 1, :status, :received_at, :created_at)',
            [
                'provider' => $this->gateway->providerName(),
                'event_id' => $event['event_id'],
                'event_type' => $event['event_type'],
                'stokvel_id' => $stokvelId,
                'payload' => $rawPayload,
                'status' => 'processing',
                'received_at' => now_utc(),
                'created_at' => now_utc(),
            ]
        );

        try {
            $result = $this->processEvent($stokvelId, $event, $processedByUserId);
            $this->db->exec(
                'UPDATE webhook_events SET processing_status = :status, processed_at = :processed_at, resource_id = :resource_id
                 WHERE id = :id',
                [
                    'status' => 'processed',
                    'processed_at' => now_utc(),
                    'resource_id' => (string) ($result['payment_id'] ?? ''),
                    'id' => $eventRowId,
                ]
            );

            return array_merge(['duplicate' => false], $result);
        } catch (\Throwable $e) {
            $this->db->exec(
                'UPDATE webhook_events SET processing_status = :status, failure_reason = :reason, processed_at = :processed_at
                 WHERE id = :id',
                [
                    'status' => 'failed',
                    'reason' => $e->getMessage(),
                    'processed_at' => now_utc(),
                    'id' => $eventRowId,
                ]
            );
            throw $e;
        }
    }

    /** @param array<string, mixed> $event */
    private function processEvent(int $stokvelId, array $event, int $processedByUserId): array
    {
        if ($event['event_type'] !== 'payment.paid' || $event['status'] !== 'paid') {
            return ['ignored' => true, 'reason' => 'Unsupported or non-paid event'];
        }

        if ($this->gateway->providerName() === MockPaymentGateway::PROVIDER) {
            $configured = (string) env('PAYMENT_GATEWAY', 'mock');
            if ($configured !== MockPaymentGateway::PROVIDER) {
                throw new FinanceException('Mock provider webhook rejected: gateway not configured as mock.');
            }
        }

        $memberId = (int) $event['member_id'];
        if ($memberId <= 0) {
            throw new FinanceException('Webhook missing member_id.');
        }

        $idempotencyKey = $this->gateway->providerName() . ':' . (string) $event['payment_id'];
        $payment = $this->contributions->capturePayment(
            $stokvelId,
            $memberId,
            (string) $event['amount'],
            'card',
            $processedByUserId > 0 ? $processedByUserId : $memberId,
            $idempotencyKey,
            (string) $event['reference'],
            (string) $event['paid_at']
        );

        return [
            'payment_id' => (int) $payment['id'],
            'receipt_number' => (string) $payment['receipt_number'],
            'allocated_amount' => (string) $payment['allocated_amount'],
            'credit_amount' => (string) $payment['credit_amount'],
        ];
    }
}
