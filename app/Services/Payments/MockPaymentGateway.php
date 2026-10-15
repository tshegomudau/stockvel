<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

final class MockPaymentGateway implements PaymentGatewayInterface
{
    public const PROVIDER = 'mock';

    public function providerName(): string
    {
        return self::PROVIDER;
    }

    /** @param array<string, mixed> $payload */
    public function initiate(int $stokvelId, array $payload): array
    {
        $amount = MoneyMath::normalize((string) ($payload['amount'] ?? '0'));
        if (!MoneyMath::isPositive($amount)) {
            throw new FinanceException('Payment amount must be positive.');
        }

        $reference = (string) ($payload['reference'] ?? uuid());
        $paymentId = 'mock_pay_' . bin2hex(random_bytes(8));
        $now = now_utc();

        $body = [
            'provider' => self::PROVIDER,
            'stokvel_id' => $stokvelId,
            'payment_id' => $paymentId,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => (string) ($payload['currency'] ?? 'ZAR'),
            'status' => 'pending',
            'checkout_url' => url('/payments/mock/checkout/' . $paymentId),
            'created_at' => $now,
        ];

        return [
            'success' => true,
            'provider' => self::PROVIDER,
            'payment_id' => $paymentId,
            'reference' => $reference,
            'checkout_url' => $body['checkout_url'],
            'raw' => $body,
        ];
    }

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $secret = (string) env('MOCK_PAYMENT_WEBHOOK_SECRET', 'mock-dev-secret');
        $expected = hash_hmac('sha256', $rawPayload, $secret);
        if (hash_equals($expected, $signatureHeader)) {
            return true;
        }

        if (str_starts_with($signatureHeader, 'sha256=')) {
            return hash_equals($expected, substr($signatureHeader, 7));
        }

        return false;
    }

    public function parseWebhookPayload(string $rawPayload): array
    {
        $data = json_decode($rawPayload, true);
        if (!is_array($data)) {
            throw new FinanceException('Invalid webhook JSON payload.');
        }

        return [
            'provider' => (string) ($data['provider'] ?? self::PROVIDER),
            'event_id' => (string) ($data['event_id'] ?? ''),
            'event_type' => (string) ($data['event_type'] ?? ''),
            'payment_id' => (string) ($data['payment_id'] ?? ''),
            'reference' => (string) ($data['reference'] ?? ''),
            'stokvel_id' => isset($data['stokvel_id']) ? (int) $data['stokvel_id'] : 0,
            'member_id' => isset($data['member_id']) ? (int) $data['member_id'] : 0,
            'amount' => MoneyMath::normalize((string) ($data['amount'] ?? '0')),
            'status' => (string) ($data['status'] ?? ''),
            'paid_at' => (string) ($data['paid_at'] ?? now_utc()),
            'raw' => $data,
        ];
    }

    /** @return array<string, mixed> */
    public function buildPaidWebhookPayload(
        int $stokvelId,
        int $memberId,
        string $paymentId,
        string $reference,
        string $amount
    ): array {
        return [
            'provider' => self::PROVIDER,
            'event_id' => uuid(),
            'event_type' => 'payment.paid',
            'payment_id' => $paymentId,
            'reference' => $reference,
            'stokvel_id' => $stokvelId,
            'member_id' => $memberId,
            'amount' => MoneyMath::normalize($amount),
            'status' => 'paid',
            'paid_at' => now_utc(),
        ];
    }

    public function signPayload(string $rawPayload): string
    {
        $secret = (string) env('MOCK_PAYMENT_WEBHOOK_SECRET', 'mock-dev-secret');

        return hash_hmac('sha256', $rawPayload, $secret);
    }
}
