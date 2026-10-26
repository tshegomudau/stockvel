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
        'funds.manage',
        'fines.manage',
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
        'constitution.manage',
        'announcements.manage',
        'documents.view',
        'documents.upload',
        'reports.view',
        'reports.export',
        'health.view',
        'goals.view',
        'goals.manage',
        'settings.manage',
        'roles.manage',
        'categories.manage',
        'notifications.view',
        'support.manage',
        'burial.manage',
        'grocery.manage',
    ];

    /** Human labels for roles UI (code remains the stored value). */
    public const PERMISSION_LABELS = [
        'members.view' => 'Members — view',
        'members.invite' => 'Members — invite',
        'members.manage' => 'Members — manage',
        'fees.manage' => 'Fees & charges — manage',
        'funds.manage' => 'Funds / pockets — manage',
        'fines.manage' => 'Fines — manage',
        'contributions.view' => 'Contributions — view',
        'contributions.capture' => 'Contributions — capture',
        'contributions.import' => 'Contributions — import',
        'expenses.manage' => 'Expenses — manage',
        'approvals.view' => 'Approvals — view',
        'approvals.action' => 'Approvals — action',
        'loans.manage' => 'Loans — manage',
        'payouts.manage' => 'Payouts — manage',
        'documents.manage_categories' => 'Documents — manage categories',
        'beneficiaries.view' => 'Beneficiaries — view',
        'beneficiaries.manage' => 'Beneficiaries — manage',
        'meetings.manage_minutes' => 'Meetings — minutes',
        'meetings.manage' => 'Meetings — manage',
        'rules.manage' => 'Rules — manage',
        'constitution.manage' => 'Constitution — manage',
        'announcements.manage' => 'Announcements — manage',
        'documents.view' => 'Documents — view',
        'documents.upload' => 'Documents — upload',
        'reports.view' => 'Reports — view',
        'reports.export' => 'Reports — export',
        'health.view' => 'Group health — view',
        'goals.view' => 'Saving goals — view',
        'goals.manage' => 'Saving goals — manage',
        'settings.manage' => 'Settings — manage',
        'roles.manage' => 'Roles & permissions — manage',
        'categories.manage' => 'Categories — manage catalogues',
        'notifications.view' => 'Notifications — view',
        'support.manage' => 'Support — manage',
        'burial.manage' => 'Burial claims — manage',
        'grocery.manage' => 'Grocery cycles — manage',
    ];

    /**
     * Platform-only permissions. Never granted via stokvel membership roles.
     *
     * @var list<string>
     */
    public const PLATFORM_PERMISSIONS = [
        'audit_logs.view',
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'chairperson' => ['*'],
        'treasurer' => [
            'members.view',
            'fees.manage',
            'funds.manage',
            'fines.manage',
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
            'health.view',
            'goals.view',
            'goals.manage',
            'categories.manage',
            'documents.view',
            'beneficiaries.view',
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
            'constitution.manage',
            'announcements.manage',
            'documents.view',
            'documents.upload',
            'documents.manage_categories',
            'categories.manage',
            'roles.manage',
            'reports.view',
            'health.view',
            'goals.view',
            'notifications.view',
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
            'announcements.manage',
            'reports.view',
            'health.view',
            'goals.view',
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
            'goals.view',
            'notifications.view',
        ],
        'auditor' => [
            'members.view',
            'contributions.view',
            'reports.view',
            'reports.export',
            'health.view',
            'funds.manage',
            'documents.view',
        ],
        // Aliases for older role codes
        'committee' => [
            'members.view',
            'approvals.view',
            'approvals.action',
            'meetings.manage',
            'expenses.manage',
            'loans.manage',
            'announcements.manage',
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
        'platform_super_admin' => ['*', 'audit_logs.view'],
        'super_admin' => ['*', 'audit_logs.view'],
        'platform_support' => [
            'members.view',
            'contributions.view',
            'reports.view',
            'documents.view',
            'notifications.view',
            // No audit_logs.view — support may assist tenants without raw audit access
        ],
        'support' => [
            'members.view',
            'contributions.view',
            'reports.view',
            'documents.view',
            'notifications.view',
        ],
        'platform_auditor' => [
            'members.view',
            'contributions.view',
            'reports.view',
            'reports.export',
            'documents.view',
            'audit_logs.view',
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
            // Platform audit is never granted via stokvel role_permissions rows
            'view_audit' => [],
            'audit.view' => [],
            'audit_logs.view' => [],
            default => str_contains($code, '.') ? [$code] : [],
        };
    }

    public function platformCan(string $permission): bool
    {
        $role = (string) ($this->tenant->platformRole() ?? '');
        if ($role === '') {
            return false;
        }
        $codes = $this->forPlatformRole($role);
        return in_array('*', $codes, true) || in_array($permission, $codes, true);
    }

    public function requirePlatformPermission(string $permission): void
    {
        if (!$this->platformCan($permission)) {
            throw new AuthorizationException('You do not have permission: ' . $permission);
        }
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
