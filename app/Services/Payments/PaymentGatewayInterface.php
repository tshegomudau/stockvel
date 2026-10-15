<?php

declare(strict_types=1);

namespace App\Services\Payments;

interface PaymentGatewayInterface
{
    /** @param array<string, mixed> $payload */
    public function initiate(int $stokvelId, array $payload): array;

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool;

    /** @return array<string, mixed> */
    public function parseWebhookPayload(string $rawPayload): array;

    public function providerName(): string;
}
