<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AuditService;
use App\Services\Auth\AuthService;
use App\Services\PermissionService;

/**
 * Manage platform administrator users (distinct from stokvel chairpersons).
 */
final class AdminUserController extends Controller
{
    private const ASSIGNABLE_ROLES = [
        'platform_super_admin',
        'platform_support',
        'platform_auditor',
    ];

    public function index(Request $request): Response
    {
        $this->gate('platform_users.view');
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $search = trim((string) $request->query('q', ''));
        $params = [];
        $where = "u.platform_role IS NOT NULL AND u.platform_role <> ''";
        if ($search !== '') {
            $where .= ' AND (u.email LIKE :q OR p.first_name LIKE :q OR p.last_name LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE {$where}",
            "SELECT u.id, u.email, u.platform_role, u.status, u.created_at, p.first_name, p.last_name
             FROM users u
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE {$where}
             ORDER BY u.created_at DESC",
            $params,
            $page,
            $perPage,
            url('/platform/admins'),
            $search !== '' ? ['q' => $search] : []
        );

        return $this->render('platform/admins/index', [
            'title' => 'Platform admins',
            'activeNav' => 'platform-admins',
            'navContext' => 'platform',
            'paginator' => $paginator,
            'search' => $search,
            'canManage' => app()->container()->get(PermissionService::class)->platformCan('platform_users.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->gate('platform_users.manage');
        return $this->render('platform/admins/form', [
            'title' => 'Add platform admin',
            'activeNav' => 'platform-admins',
            'navContext' => 'platform',
            'adminUser' => null,
            'roles' => self::ASSIGNABLE_ROLES,
            'permissionCatalog' => PermissionService::PLATFORM_PERMISSIONS,
            'permissionLabels' => PermissionService::PLATFORM_PERMISSION_LABELS,
            'grantedOverrides' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $this->gate('platform_users.manage');
        $data = [
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
            'password' => (string) $request->input('password', ''),
            'platform_role' => trim((string) $request->input('platform_role', '')),
        ];
        $validator = Validator::make($data, [
            'first_name' => 'required|min:2',
            'last_name' => 'required|min:2',
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);
        if ($validator->fails() || !in_array($data['platform_role'], self::ASSIGNABLE_ROLES, true)) {
            $this->flashOld($request);
            Session::flash('error', 'Check the form. Role must be a valid platform role.');
            return $this->redirect('/platform/admins/create');
        }
        if (!$this->mayAssignRole($data['platform_role'])) {
            Session::flash('error', 'You cannot assign a role above your own access.');
            return $this->redirect('/platform/admins/create');
        }

        $exists = $this->db()->fetch('SELECT id FROM users WHERE email = :e LIMIT 1', ['e' => $data['email']]);
        if ($exists) {
            Session::flash('error', 'Could not create that administrator.');
            return $this->redirect('/platform/admins/create');
        }

        $now = now_utc();
        $userId = (int) $this->db()->insert(
            'INSERT INTO users (email, password_hash, platform_role, status, email_verified_at, created_at, updated_at)
             VALUES (:email, :ph, :role, :status, :ev, :ca, :ua)',
            [
                'email' => $data['email'],
                'ph' => AuthService::hashPassword($data['password']),
                'role' => $data['platform_role'],
                'status' => 'active',
                'ev' => $now,
                'ca' => $now,
                'ua' => $now,
            ]
        );
        $this->db()->insert(
            'INSERT INTO user_profiles (user_id, first_name, last_name, created_at, updated_at)
             VALUES (:id, :fn, :ln, :ca, :ua)',
            ['id' => $userId, 'fn' => $data['first_name'], 'ln' => $data['last_name'], 'ca' => $now, 'ua' => $now]
        );
        $this->syncOverrides($userId, $request);
        app()->container()->get(AuditService::class)->log(
            'platform_user.created',
            'user',
            $userId,
            metadata: ['platform_role' => $data['platform_role']],
            userId: (int) $this->userId()
        );
        Session::flash('success', 'Platform admin created.');
        return $this->redirect('/platform/admins/' . $userId . '/edit');
    }

    public function edit(Request $request, string $id): Response
    {
        $this->gate('platform_users.view');
        $admin = $this->loadAdmin((int) $id);
        if (!$admin) {
            return $this->redirect('/platform/admins');
        }
        $overrides = [];
        try {
            $rows = $this->db()->fetchAll(
                'SELECT permission_code, granted FROM platform_user_permissions WHERE user_id = :uid',
                ['uid' => (int) $id]
            );
            foreach ($rows as $row) {
                if ((int) ($row['granted'] ?? 0) === 1) {
                    $overrides[] = (string) $row['permission_code'];
                }
            }
        } catch (\Throwable) {
        }

        return $this->render('platform/admins/form', [
            'title' => 'Edit platform admin',
            'activeNav' => 'platform-admins',
            'navContext' => 'platform',
            'adminUser' => $admin,
            'roles' => self::ASSIGNABLE_ROLES,
            'permissionCatalog' => PermissionService::PLATFORM_PERMISSIONS,
            'permissionLabels' => PermissionService::PLATFORM_PERMISSION_LABELS,
            'grantedOverrides' => $overrides,
            'canManage' => app()->container()->get(PermissionService::class)->platformCan('platform_users.manage'),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->gate('platform_users.manage');
        $admin = $this->loadAdmin((int) $id);
        if (!$admin) {
            return $this->redirect('/platform/admins');
        }
        $role = trim((string) $request->input('platform_role', ''));
        $status = trim((string) $request->input('status', 'active'));
        if (!in_array($role, self::ASSIGNABLE_ROLES, true)) {
            Session::flash('error', 'Invalid platform role.');
            return $this->redirect('/platform/admins/' . $id . '/edit');
        }
        if (!in_array($status, ['active', 'inactive', 'suspended'], true)) {
            $status = 'active';
        }
        if (!$this->mayAssignRole($role)) {
            Session::flash('error', 'You cannot assign a role above your own access.');
            return $this->redirect('/platform/admins/' . $id . '/edit');
        }

        /** @var PermissionService $perms */
        $perms = app()->container()->get(PermissionService::class);
        $wasSuper = in_array((string) $admin['platform_role'], ['platform_super_admin', 'super_admin'], true);
        $willBeSuper = in_array($role, ['platform_super_admin', 'super_admin'], true);
        $deactivating = $status !== 'active';
        if (($wasSuper && (!$willBeSuper || $deactivating)) && $perms->countActiveSuperAdmins() <= 1) {
            Session::flash('error', 'Cannot demote or deactivate the last active super administrator.');
            return $this->redirect('/platform/admins/' . $id . '/edit');
        }
        if ($deactivating && $perms->countActivePlatformAdmins() <= 1) {
            Session::flash('error', 'Cannot deactivate the last active platform administrator.');
            return $this->redirect('/platform/admins/' . $id . '/edit');
        }

        $now = now_utc();
        $this->db()->exec(
            'UPDATE users SET platform_role = :role, status = :status, updated_at = :ua WHERE id = :id',
            ['role' => $role, 'status' => $status, 'ua' => $now, 'id' => (int) $id]
        );
        $fn = trim((string) $request->input('first_name', ''));
        $ln = trim((string) $request->input('last_name', ''));
        if ($fn !== '' && $ln !== '') {
            $this->db()->exec(
                'UPDATE user_profiles SET first_name = :fn, last_name = :ln, updated_at = :ua WHERE user_id = :id',
                ['fn' => $fn, 'ln' => $ln, 'ua' => $now, 'id' => (int) $id]
            );
        }
        $password = (string) $request->input('password', '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                Session::flash('error', 'Password must be at least 8 characters.');
                return $this->redirect('/platform/admins/' . $id . '/edit');
            }
            $this->db()->exec(
                'UPDATE users SET password_hash = :ph, updated_at = :ua WHERE id = :id',
                ['ph' => AuthService::hashPassword($password), 'ua' => $now, 'id' => (int) $id]
            );
        }
        $this->syncOverrides((int) $id, $request);
        app()->container()->get(AuditService::class)->log(
            'platform_user.updated',
            'user',
            (int) $id,
            oldValues: ['platform_role' => $admin['platform_role'], 'status' => $admin['status']],
            newValues: ['platform_role' => $role, 'status' => $status],
            userId: (int) $this->userId()
        );
        Session::flash('success', 'Platform admin updated.');
        return $this->redirect('/platform/admins/' . $id . '/edit');
    }

    private function gate(string $permission): void
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            throw new \App\Exceptions\AuthorizationException('Platform access required.');
        }
        app()->container()->get(PermissionService::class)->requirePlatformPermission($permission);
    }

    private function mayAssignRole(string $role): bool
    {
        $mine = (string) ($this->tenant()->platformRole() ?? '');
        if (in_array($mine, ['platform_super_admin', 'super_admin'], true)) {
            return true;
        }
        // Non-supers cannot create supers.
        return !in_array($role, ['platform_super_admin', 'super_admin'], true)
            && app()->container()->get(PermissionService::class)->platformCan('platform_users.manage');
    }

    /** @return array<string, mixed>|null */
    private function loadAdmin(int $id): ?array
    {
        return $this->db()->fetch(
            "SELECT u.*, p.first_name, p.last_name
             FROM users u
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE u.id = :id AND u.platform_role IS NOT NULL AND u.platform_role <> ''
             LIMIT 1",
            ['id' => $id]
        );
    }

    private function syncOverrides(int $userId, Request $request): void
    {
        $selected = $request->input('permissions', []);
        if (!is_array($selected)) {
            $selected = [];
        }
        $selected = array_values(array_intersect(
            array_map('strval', $selected),
            PermissionService::PLATFORM_PERMISSIONS
        ));
        // Only grant permissions the actor holds (unless super).
        $actor = app()->container()->get(PermissionService::class);
        $filtered = [];
        foreach ($selected as $code) {
            if ($actor->platformCan($code) || $actor->platformCan('*')) {
                $filtered[] = $code;
            }
        }
        try {
            $this->db()->exec('DELETE FROM platform_user_permissions WHERE user_id = :uid', ['uid' => $userId]);
            $now = now_utc();
            foreach ($filtered as $code) {
                // Skip codes already implied by role to keep table sparse? Keep explicit grants.
                $this->db()->insert(
                    'INSERT INTO platform_user_permissions (user_id, permission_code, granted, granted_by_user_id, created_at, updated_at)
                     VALUES (:uid, :code, 1, :by, :ca, :ua)',
                    [
                        'uid' => $userId,
                        'code' => $code,
                        'by' => $this->userId(),
                        'ca' => $now,
                        'ua' => $now,
                    ]
                );
            }
        } catch (\Throwable) {
            // Table may be missing until migration 022.
        }
    }
}
