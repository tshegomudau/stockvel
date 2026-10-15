<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext as CoreTenantContext;
use App\Exceptions\AuthorizationException;

final class PermissionService
{
    /** @var array<string, string[]> */
    private const ROLE_PERMISSIONS = [
        'chairperson' => ['*'],
        'treasurer' => [
            'finance.view',
            'finance.post',
            'contributions.manage',
            'expenses.approve',
            'reports.view',
            'members.view',
        ],
        'secretary' => [
            'members.manage',
            'members.invite',
            'meetings.manage',
            'documents.manage',
            'reports.view',
        ],
        'committee' => [
            'members.view',
            'meetings.view',
            'loans.review',
            'expenses.review',
        ],
        'auditor' => [
            'finance.view',
            'reports.view',
            'audit.view',
            'members.view',
        ],
        'member' => [
            'dashboard.view',
            'profile.view',
            'contributions.view',
            'statements.view',
            'documents.view',
        ],
        'ordinary' => [
            'profile.view',
            'contributions.view',
            'statements.view',
        ],
    ];

    public function __construct(
        private readonly CoreTenantContext $tenant,
        private readonly Database $db
    ) {
    }

    public function can(string $permission): bool
    {
        return $this->tenant->can($permission);
    }

    public function require(string $permission): void
    {
        if (!$this->can($permission)) {
            throw new AuthorizationException('You do not have permission: ' . $permission);
        }
    }

    /** @return string[] */
    public function codesForMembership(array $membership): array
    {
        if (!empty($membership['permissions'])) {
            $decoded = is_string($membership['permissions'])
                ? json_decode($membership['permissions'], true)
                : $membership['permissions'];
            if (is_array($decoded) && $decoded !== []) {
                return array_values(array_unique(array_map('strval', $decoded)));
            }
        }

        $role = $this->resolveRoleCode($membership);
        $codes = self::ROLE_PERMISSIONS[$role] ?? self::ROLE_PERMISSIONS['member'];

        try {
            $rows = $this->db->fetchAll(
                'SELECT p.code
                 FROM membership_permissions mp
                 INNER JOIN permissions p ON p.id = mp.permission_id
                 WHERE mp.membership_id = :membership_id AND mp.stokvel_id = :stokvel_id AND mp.granted = 1',
                [
                    'membership_id' => (int) $membership['id'],
                    'stokvel_id' => (int) $membership['stokvel_id'],
                ]
            );
            foreach ($rows as $row) {
                $codes[] = (string) $row['code'];
            }
        } catch (\Throwable) {
            try {
                $rows = $this->db->fetchAll(
                    'SELECT permission_code FROM membership_permissions
                     WHERE membership_id = :membership_id AND stokvel_id = :stokvel_id',
                    [
                        'membership_id' => (int) $membership['id'],
                        'stokvel_id' => (int) $membership['stokvel_id'],
                    ]
                );
                foreach ($rows as $row) {
                    $codes[] = (string) $row['permission_code'];
                }
            } catch (\Throwable) {
            }
        }

        return array_values(array_unique($codes));
    }

    /** @return string[] */
    public function forRole(string $role): array
    {
        $role = strtolower($role);
        return self::ROLE_PERMISSIONS[$role] ?? self::ROLE_PERMISSIONS['member'];
    }

    private function resolveRoleCode(array $membership): string
    {
        if (!empty($membership['role'])) {
            return strtolower((string) $membership['role']);
        }

        try {
            $row = $this->db->fetch(
                'SELECT r.code FROM membership_roles mr
                 INNER JOIN roles r ON r.id = mr.role_id
                 WHERE mr.membership_id = :membership_id
                 ORDER BY mr.id ASC LIMIT 1',
                ['membership_id' => (int) $membership['id']]
            );
            if ($row && !empty($row['code'])) {
                return strtolower((string) $row['code']);
            }
        } catch (\Throwable) {
        }

        return 'member';
    }
}
