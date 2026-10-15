<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Core\TenantContext as CoreTenantContext;
use App\Exceptions\TenantException;
use App\Repositories\StokvelRepository;

final class TenantContext
{
    private const SESSION_STOKVEL_KEY = 'active_stokvel_id';

    public function __construct(
        private readonly Database $db,
        private readonly StokvelRepository $stokvels,
        private readonly CoreTenantContext $coreTenant
    ) {
    }

    public function getActiveStokvelId(): ?int
    {
        $id = Session::get(self::SESSION_STOKVEL_KEY) ?? Session::get('stokvel_id');
        if (is_numeric($id)) {
            return (int) $id;
        }
        return $this->coreTenant->stokvelId();
    }

    public function setActiveStokvelId(?int $stokvelId): void
    {
        if ($stokvelId === null) {
            Session::forget(self::SESSION_STOKVEL_KEY);
            Session::forget('stokvel_id');
            $this->coreTenant->setTenant(null);
            return;
        }
        Session::set(self::SESSION_STOKVEL_KEY, $stokvelId);
        Session::set('stokvel_id', $stokvelId);
    }

    public function switchForUser(int $userId, int $stokvelId): void
    {
        $membership = $this->resolveMembershipForUser($userId, $stokvelId);
        if ($membership === null) {
            throw new TenantException('You do not belong to this stokvel.');
        }

        $stokvel = $this->getStokvel($stokvelId);
        if ($stokvel === null) {
            throw new TenantException('Stokvel not found.');
        }

        $this->setActiveStokvelId($stokvelId);
        $this->coreTenant->setTenant($stokvel, $membership);
    }

    public function resolveMembershipForUser(int $userId, int $stokvelId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM memberships
             WHERE user_id = :user_id AND stokvel_id = :stokvel_id LIMIT 1',
            [
                'user_id' => $userId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    public function getStokvel(int $stokvelId): ?array
    {
        return $this->stokvels->findById($stokvelId);
    }

    public function clear(): void
    {
        $this->setActiveStokvelId(null);
    }
}
