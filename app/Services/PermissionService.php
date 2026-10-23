<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\TenantContext as CoreTenantContext;
use App\Exceptions\AuthorizationException;
use App\Support\ModuleRegistry;

final class PermissionService
{
    /** @var list<string> */
    public const APP_PERMISSIONS = [
        'members.view',
        'members.invite',
        'members.manage',
        'fees.manage',
        'contributions.view',
        'contributions.capture',
        'contributions.import',
        'expenses.manage',
        'approvals.view',
        'approvals.action',
        'loans.manage',
        'payouts.manage',
        'documents.manage_categories',
        'beneficiaries.view',
        'beneficiaries.manage',
        'meetings.manage_minutes',
        'meetings.manage',
        'rules.manage',
        'documents.view',
        'documents.upload',
        'reports.view',
        'reports.export',
        'settings.manage',
        'notifications.view',
        'onboarding.manage',
        'audit.view',
        'support.manage',
        'burial.manage',
        'grocery.manage',
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'chairperson' => ['*'],
        'treasurer' => [
            'members.view',
            'fees.manage',
            'contributions.view',
            'contributions.capture',
            'contributions.import',
            'expenses.manage',
            'approvals.view',
            'approvals.action',
            'loans.manage',
            'payouts.manage',
            'reports.view',
            'reports.export',
            'documents.view',
            'beneficiaries.view',
            'audit.view',
            'notifications.view',
        ],
        'secretary' => [
            'members.view',
            'members.invite',
            'members.manage',
            'beneficiaries.view',
            'beneficiaries.manage',
            'meetings.manage',
            'meetings.manage_minutes',
            'rules.manage',
            'documents.view',
            'documents.upload',
            'documents.manage_categories',
            'reports.view',
            'notifications.view',
            'audit.view',
            'support.manage',
            'burial.manage',
            'grocery.manage',
        ],
        'committee_member' => [
            'members.view',
            'approvals.view',
            'approvals.action',
            'meetings.manage',
            'meetings.manage_minutes',
            'expenses.manage',
            'loans.manage',
            'reports.view',
            'documents.view',
            'beneficiaries.view',
            'notifications.view',
            'burial.manage',
            'grocery.manage',
        ],
        'ordinary_member' => [
            'members.invite',
            'contributions.view',
            'documents.view',
            'notifications.view',
        ],
        'auditor' => [
            'members.view',
            'contributions.view',
            'reports.view',
            'reports.export',
            'documents.view',
            'audit.view',
        ],
        // Aliases for older role codes
        'committee' => [
            'members.view',
            'approvals.view',
            'approvals.action',
            'meetings.manage',
            'expenses.manage',
            'loans.manage',
            'reports.view',
            'documents.view',
            'notifications.view',
        ],
        'member' => [
            'members.invite',
            'contributions.view',
            'documents.view',
            'notifications.view',
        ],
        'ordinary' => [
            'members.invite',
            'contributions.view',
            'documents.view',
            'notifications.view',
        ],
    ];

    /** @var array<string, list<string>> */
    private const PLATFORM_TENANT_PERMISSIONS = [
        'platform_super_admin' => ['*'],
        'super_admin' => ['*'],
        'platform_support' => [
            'members.view',
            'contributions.view',
            'reports.view',
            'documents.view',
            'audit.view',
            'notifications.view',
        ],
        'support' => [
            'members.view',
            'contributions.view',
            'reports.view',
            'documents.view',
            'audit.view',
            'notifications.view',
        ],
        'platform_auditor' => [
            'members.view',
            'contributions.view',
            'reports.view',
            'reports.export',
            'documents.view',
            'audit.view',
        ],
        'auditor' => [
            'members.view',
            'contributions.view',
            'reports.view',
            'reports.export',
            'documents.view',
            'audit.view',
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

    public function isModuleEnabled(string $moduleKey): bool
    {
        return $this->tenant->moduleEnabled($moduleKey);
    }

    /**
     * @return array<string, bool>
     */
    public function enabledModules(): array
    {
        $cached = $this->tenant->enabledModules();
        if ($cached !== []) {
            return $cached;
        }
        return ModuleRegistry::defaults(true);
    }

    /** @return list<string> */
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
        $codes = self::ROLE_PERMISSIONS[$role] ?? self::ROLE_PERMISSIONS['ordinary_member'];

        try {
            $stokvelId = (int) ($membership['stokvel_id'] ?? 0);
            if ($stokvelId > 0) {
                $settingsRow = $this->db->fetch(
                    'SELECT settings_json FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
                    ['sid' => $stokvelId]
                );
                $settingsJson = json_decode((string) ($settingsRow['settings_json'] ?? '{}'), true);
                if (is_array($settingsJson['role_permissions'][$role] ?? null)) {
                    $codes = $settingsJson['role_permissions'][$role];
                }
            }
        } catch (\Throwable) {
        }

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
            // Optional override table / column variants.
        }

        try {
            $rolePerms = $this->db->fetchAll(
                'SELECT p.code
                 FROM membership_roles mr
                 INNER JOIN role_permissions rp ON rp.role_id = mr.role_id
                 INNER JOIN permissions p ON p.id = rp.permission_id
                 WHERE mr.membership_id = :membership_id',
                ['membership_id' => (int) $membership['id']]
            );
            foreach ($rolePerms as $row) {
                $mapped = $this->mapLegacyPermissionCode((string) $row['code']);
                foreach ($mapped as $code) {
                    $codes[] = $code;
                }
            }
        } catch (\Throwable) {
        }

        return array_values(array_unique($codes));
    }

    /** @return list<string> */
    public function forRole(string $role): array
    {
        $role = strtolower($role);
        return self::ROLE_PERMISSIONS[$role] ?? self::ROLE_PERMISSIONS['ordinary_member'];
    }

    /** @return list<string> */
    public function forPlatformRole(string $platformRole): array
    {
        $platformRole = strtolower($platformRole);
        return self::PLATFORM_TENANT_PERMISSIONS[$platformRole] ?? [];
    }

    /** @return list<string> */
    private function mapLegacyPermissionCode(string $code): array
    {
        return match ($code) {
            'manage_members' => ['members.view', 'members.invite', 'members.manage'],
            'manage_rules' => ['rules.manage'],
            'record_contributions' => ['contributions.view', 'contributions.capture', 'contributions.import'],
            'approve_transactions' => ['approvals.view', 'approvals.action'],
            'manage_loans' => ['loans.manage'],
            'record_expenses' => ['expenses.manage'],
            'run_payouts' => ['payouts.manage'],
            'manage_meetings' => ['meetings.manage'],
            'upload_documents' => ['documents.view', 'documents.upload'],
            'view_financial_reports' => ['reports.view', 'contributions.view'],
            'export_data' => ['reports.export'],
            'view_audit' => ['audit.view'],
            default => str_contains($code, '.') ? [$code] : [],
        };
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

        return 'ordinary_member';
    }
}
