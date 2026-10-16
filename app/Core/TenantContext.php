<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;

final class TenantContext
{
    private ?int $stokvelId = null;
    private ?array $stokvel = null;
    private ?array $membership = null;
    private ?array $user = null;
    private array $permissions = [];
    private ?string $platformRole = null;

    public function setUser(?array $user): void
    {
        $this->user = $user;
        $this->platformRole = $user['platform_role'] ?? null;
    }

    public function user(): ?array
    {
        return $this->user;
    }

    public function userId(): ?int
    {
        return isset($this->user['id']) ? (int) $this->user['id'] : null;
    }

    public function setTenant(?array $stokvel, ?array $membership = null, array $permissions = []): void
    {
        $this->stokvel = $stokvel;
        $this->stokvelId = $stokvel ? (int) $stokvel['id'] : null;
        $this->membership = $membership;
        $this->permissions = $permissions;
    }

    public function stokvelId(): ?int
    {
        return $this->stokvelId;
    }

    public function requireStokvelId(): int
    {
        if ($this->stokvelId === null) {
            throw new HttpException(403, 'No active stokvel selected');
        }
        return $this->stokvelId;
    }

    public function stokvel(): ?array
    {
        return $this->stokvel;
    }

    public function membership(): ?array
    {
        return $this->membership;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    public function can(string $permission): bool
    {
        if (in_array('*', $this->permissions, true)) {
            return true;
        }
        return in_array($permission, $this->permissions, true);
    }

    public function requirePermission(string $permission): void
    {
        if (!$this->can($permission)) {
            throw new HttpException(403, 'You do not have permission to perform this action');
        }
    }

    public function platformRole(): ?string
    {
        return $this->platformRole;
    }

    public function isPlatformAdmin(): bool
    {
        return in_array($this->platformRole, [
            'platform_super_admin',
            'platform_support',
            'platform_auditor',
            'super_admin',
            'support',
            'auditor',
        ], true);
    }

    public function isSuperAdmin(): bool
    {
        return in_array($this->platformRole, ['platform_super_admin', 'super_admin'], true);
    }
}
