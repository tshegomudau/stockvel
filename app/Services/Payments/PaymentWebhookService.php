<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Services\Finance\ContributionService;

/**
 * Verifies payment webhook signatures and records events against the
 * migration-003 webhook_events schema (external_id / payload_json / status).
 */
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
            'SELECT id, status FROM webhook_events
             WHERE provider = :provider AND external_id = :external_id LIMIT 1',
            ['provider' => $this->gateway->providerName(), 'external_id' => $event['event_id']]
        );
        if ($existing !== null) {
            return [
                'duplicate' => true,
                'event_id' => $event['event_id'],
                'status' => (string) $existing['status'],
            ];
        }

        $stokvelId = (int) $event['stokvel_id'];
        if ($stokvelId <= 0) {
            throw new FinanceException('Webhook missing stokvel_id.');
        }

        $now = now_utc();
        $eventRowId = (int) $this->db->insert(
            'INSERT INTO webhook_events
                (stokvel_id, provider, event_type, external_id, payload_json, status, processed_at, error_message, created_at, updated_at)
             VALUES
                (:stokvel_id, :provider, :event_type, :external_id, :payload_json, :status, NULL, NULL, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'provider' => $this->gateway->providerName(),
                'event_type' => (string) $event['event_type'],
                'external_id' => $event['event_id'],
                'payload_json' => json_encode([
                    'payload' => json_decode($rawPayload, true) ?: $rawPayload,
                    'signature_valid' => true,
                ], JSON_THROW_ON_ERROR),
                'status' => 'received',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        try {
            $this->db->exec(
                'UPDATE webhook_events SET status = :status, updated_at = :updated_at WHERE id = :id',
                ['status' => 'processing', 'updated_at' => now_utc(), 'id' => $eventRowId]
            );

            $result = $this->processEvent($stokvelId, $event, $processedByUserId);
            $this->db->exec(
                'UPDATE webhook_events
                 SET status = :status, processed_at = :processed_at, updated_at = :updated_at, error_message = NULL
                 WHERE id = :id',
                [
                    'status' => 'processed',
                    'processed_at' => now_utc(),
                    'updated_at' => now_utc(),
                    'id' => $eventRowId,
                ]
            );

            return array_merge(['duplicate' => false, 'event_id' => $event['event_id']], $result);
        } catch (\Throwable $e) {
            $this->db->exec(
                'UPDATE webhook_events
                 SET status = :status, error_message = :reason, processed_at = :processed_at, updated_at = :updated_at
                 WHERE id = :id',
                [
                    'status' => 'failed',
                    'reason' => $e->getMessage(),
                    'processed_at' => now_utc(),
                    'updated_at' => now_utc(),
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
            $configured = (string) env('PAYMENT_GATEWAY', env('PAYMENT_DRIVER', 'mock'));
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
