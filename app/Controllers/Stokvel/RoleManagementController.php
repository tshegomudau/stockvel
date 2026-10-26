<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PermissionService;
use App\Support\ModuleRegistry;

final class RoleManagementController extends Controller
{
    /** @var list<string> */
    private const STOKVEL_ROLES = [
        'chairperson',
        'treasurer',
        'secretary',
        'committee_member',
        'ordinary_member',
        'auditor',
    ];

    /** @var array<string, string> */
    private const ROLE_LABELS = [
        'chairperson' => 'Chairperson',
        'treasurer' => 'Treasurer',
        'secretary' => 'Secretary',
        'committee_member' => 'Committee member',
        'ordinary_member' => 'Ordinary member',
        'auditor' => 'Auditor',
    ];

    public function index(Request $request): Response
    {
        $this->requireRolesAccess();
        $sid = $this->requireStokvel();
        $this->ensureSystemRoles();

        $roles = [];
        foreach (self::STOKVEL_ROLES as $code) {
            $role = $this->findSystemRole($code);
            $memberCount = 0;
            if ($role) {
                $countRow = $this->db()->fetch(
                    'SELECT COUNT(*) AS c FROM membership_roles
                     WHERE stokvel_id = :sid AND role_id = :rid',
                    ['sid' => $sid, 'rid' => (int) $role['id']]
                );
                $memberCount = (int) ($countRow['c'] ?? 0);
            }
            $roles[] = [
                'code' => $code,
                'name' => self::ROLE_LABELS[$code] ?? ucwords(str_replace('_', ' ', $code)),
                'member_count' => $memberCount,
                'id' => $role ? (int) $role['id'] : null,
            ];
        }

        return $this->render('stokvel/roles/index', [
            'title' => 'Roles & permissions',
            'activeNav' => 'roles',
            'navContext' => 'app',
            'roles' => $roles,
        ]);
    }

    public function show(Request $request, string $roleCode): Response
    {
        $this->requireRolesAccess();
        $sid = $this->requireStokvel();
        $roleCode = strtolower(trim($roleCode));
        if (!in_array($roleCode, self::STOKVEL_ROLES, true)) {
            Session::flash('error', 'Unknown role.');
            return $this->redirect('/app/settings/roles');
        }

        $this->ensureSystemRoles();
        $role = $this->findSystemRole($roleCode);
        if (!$role) {
            Session::flash('error', 'Role could not be loaded.');
            return $this->redirect('/app/settings/roles');
        }

        /** @var PermissionService $permissionService */
        $permissionService = app()->container()->get(PermissionService::class);
        $defaults = $permissionService->forRole($roleCode);
        $overrides = $this->rolePermissionOverrides($sid);
        $selected = is_array($overrides[$roleCode] ?? null) ? $overrides[$roleCode] : $defaults;
        $isFullAccess = in_array('*', $selected, true) || in_array('*', $defaults, true);

        $assignablePermissions = $this->assignablePermissions();
        $selected = array_values(array_filter(
            $selected,
            static fn ($code) => $code === '*' || in_array((string) $code, $assignablePermissions, true)
        ));

        $members = $this->db()->fetchAll(
            'SELECT m.id AS membership_id, m.member_number, m.status,
                    u.email, p.first_name, p.last_name, mr.id AS assignment_id
             FROM membership_roles mr
             INNER JOIN memberships m ON m.id = mr.membership_id
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE mr.stokvel_id = :sid AND mr.role_id = :rid
             ORDER BY p.last_name ASC, p.first_name ASC',
            ['sid' => $sid, 'rid' => (int) $role['id']]
        );

        $assignable = $this->db()->fetchAll(
            'SELECT m.id AS membership_id, m.member_number, m.status,
                    u.email, p.first_name, p.last_name
             FROM memberships m
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE m.stokvel_id = :sid
               AND m.status IN (\'active\', \'suspended\')
               AND NOT EXISTS (
                   SELECT 1 FROM membership_roles mr
                   WHERE mr.membership_id = m.id AND mr.role_id = :rid
               )
             ORDER BY p.last_name ASC, p.first_name ASC
             LIMIT 200',
            ['sid' => $sid, 'rid' => (int) $role['id']]
        );

        $permissionGroups = $this->groupPermissions($assignablePermissions);

        return $this->render('stokvel/roles/show', [
            'title' => (self::ROLE_LABELS[$roleCode] ?? $roleCode) . ' permissions',
            'activeNav' => 'roles',
            'navContext' => 'app',
            'role' => [
                'code' => $roleCode,
                'name' => self::ROLE_LABELS[$roleCode] ?? ucwords(str_replace('_', ' ', $roleCode)),
                'id' => (int) $role['id'],
                'permissions' => $selected,
                'is_full_access' => $isFullAccess,
            ],
            'permissionGroups' => $permissionGroups,
            'permissionLabels' => PermissionService::PERMISSION_LABELS,
            'members' => $members,
            'assignableMembers' => $assignable,
        ]);
    }

    public function update(Request $request, string $roleCode): Response
    {
        $this->requireRolesAccess();
        $sid = $this->requireStokvel();
        $roleCode = strtolower(trim($roleCode));
        if (!in_array($roleCode, self::STOKVEL_ROLES, true)) {
            Session::flash('error', 'Unknown role.');
            return $this->redirect('/app/settings/roles');
        }

        $selected = $request->input('permissions', []);
        if (!is_array($selected)) {
            $selected = [];
        }
        $selected = array_values(array_unique(array_map('strval', $selected)));
        $enabled = $this->tenant()->enabledModules();
        $allowed = ModuleRegistry::filterPermissions(PermissionService::APP_PERMISSIONS, $enabled);
        $selected = array_values(array_intersect($selected, $allowed));

        /** @var PermissionService $permissionService */
        $permissionService = app()->container()->get(PermissionService::class);
        if (in_array('*', $permissionService->forRole($roleCode), true) && $roleCode === 'chairperson') {
            $selected = ['*'];
        }

        $settings = $this->db()->fetch(
            'SELECT id, settings_json FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $sid]
        );
        $json = json_decode((string) ($settings['settings_json'] ?? '{}'), true);
        if (!is_array($json)) {
            $json = [];
        }
        if (!isset($json['role_permissions']) || !is_array($json['role_permissions'])) {
            $json['role_permissions'] = [];
        }
        // Keep overrides for inactive modules so re-enabling a module restores prior grants.
        if ($selected !== ['*']) {
            $previous = is_array($json['role_permissions'][$roleCode] ?? null)
                ? $json['role_permissions'][$roleCode]
                : [];
            foreach ($previous as $code) {
                $code = (string) $code;
                if ($code === '*' || $code === '') {
                    continue;
                }
                if (!ModuleRegistry::permissionAllowed($code, $enabled) && !in_array($code, $selected, true)) {
                    $selected[] = $code;
                }
            }
        }
        $json['role_permissions'][$roleCode] = array_values(array_unique($selected));
        $encoded = json_encode($json);
        if ($encoded === false) {
            Session::flash('error', 'Could not save permissions.');
            return $this->redirect('/app/settings/roles/' . rawurlencode($roleCode));
        }
        $now = now_utc();

        if ($settings) {
            $this->db()->exec(
                'UPDATE stokvel_settings SET settings_json = :json, updated_at = :ua WHERE id = :id',
                ['json' => $encoded, 'ua' => $now, 'id' => (int) $settings['id']]
            );
        } else {
            $this->db()->insert(
                'INSERT INTO stokvel_settings (stokvel_id, settings_json, created_at, updated_at)
                 VALUES (:sid, :json, :ca, :ua)',
                ['sid' => $sid, 'json' => $encoded, 'ca' => $now, 'ua' => $now]
            );
        }

        Session::flash('success', 'Permissions updated for ' . (self::ROLE_LABELS[$roleCode] ?? $roleCode) . '.');
        return $this->redirect('/app/settings/roles/' . rawurlencode($roleCode));
    }

    public function assign(Request $request, string $roleCode): Response
    {
        $this->requireRolesAccess();
        $sid = $this->requireStokvel();
        $roleCode = strtolower(trim($roleCode));
        if (!in_array($roleCode, self::STOKVEL_ROLES, true)) {
            Session::flash('error', 'Unknown role.');
            return $this->redirect('/app/settings/roles');
        }

        $this->ensureSystemRoles();
        $role = $this->findSystemRole($roleCode);
        $membershipId = (int) $request->input('membership_id', 0);
        if (!$role || $membershipId <= 0) {
            Session::flash('error', 'Select a member to assign.');
            return $this->redirect('/app/settings/roles/' . rawurlencode($roleCode));
        }

        $membership = $this->db()->fetch(
            'SELECT id FROM memberships WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $membershipId, 'sid' => $sid]
        );
        if (!$membership) {
            Session::flash('error', 'Member not found in this stokvel.');
            return $this->redirect('/app/settings/roles/' . rawurlencode($roleCode));
        }

        $now = now_utc();
        try {
            $this->db()->insert(
                'INSERT INTO membership_roles
                    (stokvel_id, membership_id, role_id, assigned_by_user_id, assigned_at, created_at)
                 VALUES (:sid, :mid, :rid, :by, :aa, :ca)',
                [
                    'sid' => $sid,
                    'mid' => $membershipId,
                    'rid' => (int) $role['id'],
                    'by' => $this->userId(),
                    'aa' => $now,
                    'ca' => $now,
                ]
            );
            Session::flash('success', 'Member linked to role.');
        } catch (\Throwable) {
            Session::flash('error', 'Could not assign role (already linked?).');
        }

        return $this->redirect('/app/settings/roles/' . rawurlencode($roleCode));
    }

    public function remove(Request $request, string $roleCode): Response
    {
        $this->requireRolesAccess();
        $sid = $this->requireStokvel();
        $roleCode = strtolower(trim($roleCode));
        if (!in_array($roleCode, self::STOKVEL_ROLES, true)) {
            Session::flash('error', 'Unknown role.');
            return $this->redirect('/app/settings/roles');
        }

        $this->ensureSystemRoles();
        $role = $this->findSystemRole($roleCode);
        $membershipId = (int) $request->input('membership_id', 0);
        if (!$role || $membershipId <= 0) {
            Session::flash('error', 'Select a member to remove.');
            return $this->redirect('/app/settings/roles/' . rawurlencode($roleCode));
        }

        $this->db()->exec(
            'DELETE FROM membership_roles
             WHERE stokvel_id = :sid AND role_id = :rid AND membership_id = :mid',
            ['sid' => $sid, 'rid' => (int) $role['id'], 'mid' => $membershipId]
        );
        Session::flash('success', 'Member unlinked from role.');
        return $this->redirect('/app/settings/roles/' . rawurlencode($roleCode));
    }

    private function ensureSystemRoles(): void
    {
        $now = now_utc();
        foreach (self::STOKVEL_ROLES as $code) {
            $existing = $this->findSystemRole($code);
            if ($existing) {
                continue;
            }
            $name = self::ROLE_LABELS[$code] ?? ucwords(str_replace('_', ' ', $code));
            try {
                $this->db()->insert(
                    'INSERT INTO roles (stokvel_id, code, name, description, is_system, created_at, updated_at)
                     VALUES (NULL, :code, :name, :desc, 1, :ca, :ua)',
                    [
                        'code' => $code,
                        'name' => $name,
                        'desc' => 'System stokvel role: ' . $name,
                        'ca' => $now,
                        'ua' => $now,
                    ]
                );
            } catch (\Throwable) {
                // Concurrent create / unique race — re-check below.
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function findSystemRole(string $code): ?array
    {
        return $this->db()->fetch(
            'SELECT * FROM roles WHERE code = :code AND stokvel_id IS NULL LIMIT 1',
            ['code' => $code]
        );
    }

    /** @return array<string, list<string>> */
    private function rolePermissionOverrides(int $stokvelId): array
    {
        $settings = $this->db()->fetch(
            'SELECT settings_json FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        );
        $json = json_decode((string) ($settings['settings_json'] ?? '{}'), true);
        if (!is_array($json) || !is_array($json['role_permissions'] ?? null)) {
            return [];
        }
        return $json['role_permissions'];
    }

    /**
     * @param list<string> $permissions
     * @return array<string, list<string>>
     */
    private function groupPermissions(array $permissions): array
    {
        $groups = [];
        foreach ($permissions as $perm) {
            $parts = explode('.', $perm, 2);
            $group = $parts[0] ?? 'other';
            $groups[$group][] = $perm;
        }
        ksort($groups);
        return $groups;
    }

    private function requireRolesAccess(): void
    {
        if ($this->tenant()->can('roles.manage') || $this->tenant()->can('settings.manage')) {
            return;
        }
        $this->requirePermission('roles.manage');
    }

    /** @return list<string> */
    private function assignablePermissions(): array
    {
        $enabled = $this->tenant()->enabledModules();
        return ModuleRegistry::filterPermissions(PermissionService::APP_PERMISSIONS, $enabled);
    }
}
