<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext as CoreTenantContext;
use App\Repositories\AuditRepository;

final class AuditService
{
    /** @var string[] */
    private const REDACT_KEYS = [
        'password',
        'password_hash',
        'password_confirmation',
        'token',
        'token_hash',
        'remember_token',
        'api_key',
        'secret',
        'bank_account',
        'bank_account_number',
        'account_number',
        'id_number',
        'otp',
        'cvv',
    ];

    public function __construct(
        private readonly AuditRepository $audit,
        private readonly CoreTenantContext $tenantContext
    ) {
    }

    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?int $stokvelId = null
    ): void {
        $this->audit->append([
            'stokvel_id' => $stokvelId ?? $this->tenantContext->stokvelId(),
            'user_id' => $this->tenantContext->userId(),
            'action' => $action,
            'entity_type' => $entityType ?? 'system',
            'entity_id' => $entityId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 512) : null,
            'old_values' => $this->redact($oldValues),
            'new_values' => $this->redact($newValues),
            'metadata' => $this->redact($metadata),
        ]);
    }

    /** @param array<string, mixed>|null $data */
    private function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $redacted = [];
        foreach ($data as $key => $value) {
            $normalized = strtolower((string) $key);
            if ($this->shouldRedactKey($normalized)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                $redacted[$key] = $this->redact($value);
                continue;
            }
            $redacted[$key] = $value;
        }

        return $redacted;
    }

    private function shouldRedactKey(string $key): bool
    {
        foreach (self::REDACT_KEYS as $needle) {
            if ($key === $needle || str_contains($key, $needle)) {
                return true;
            }
        }
        return false;
    }
}
