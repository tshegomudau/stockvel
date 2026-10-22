<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

/**
 * Platform may define global roles and permissions templates.
 * Day-to-day membership user management stays with each stokvel chairperson.
 */
final class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }

        $roles = $this->db()->fetchAll(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count
             FROM roles r
             WHERE r.stokvel_id IS NULL
             ORDER BY r.name ASC'
        );

        return $this->render('platform/roles/index', [
            'title' => 'Platform roles',
            'activeNav' => 'platform-roles',
            'navContext' => 'platform',
            'roles' => $roles,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            return $this->redirect('/platform/roles');
        }
        $permissions = $this->db()->fetchAll('SELECT * FROM permissions ORDER BY code ASC');
        return $this->render('platform/roles/edit', [
            'title' => 'New role',
            'activeNav' => 'platform-roles',
            'navContext' => 'platform',
            'role' => null,
            'permissions' => $permissions,
            'selected' => [],
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            return $this->redirect('/platform/roles');
        }
        $data = [
            'code' => strtolower(trim((string) $request->input('code', ''))),
            'name' => trim((string) $request->input('name', '')),
            'description' => trim((string) $request->input('description', '')),
        ];
        $validator = Validator::make($data, [
            'code' => 'required|min:2',
            'name' => 'required|min:2',
        ]);
        if ($validator->fails()) {
            Session::flash('error', 'Role code and name are required.');
            return $this->redirect('/platform/roles/create');
        }
        $now = now_utc();
        $roleId = (int) $this->db()->insert(
            'INSERT INTO roles (stokvel_id, code, name, description, is_system, created_at, updated_at)
             VALUES (NULL, :code, :name, :desc, 0, :ca, :ua)',
            [
                'code' => $data['code'],
                'name' => $data['name'],
                'desc' => $data['description'] !== '' ? $data['description'] : null,
                'ca' => $now,
                'ua' => $now,
            ]
        );
        $this->syncPermissions($roleId, $request->input('permissions', []));
        Session::flash('success', 'Role created.');
        return $this->redirect('/platform/roles/' . $roleId . '/edit');
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }
        $role = $this->db()->fetch(
            'SELECT * FROM roles WHERE id = :id AND stokvel_id IS NULL LIMIT 1',
            ['id' => (int) $id]
        );
        if (!$role) {
            return $this->redirect('/platform/roles');
        }
        $permissions = $this->db()->fetchAll('SELECT * FROM permissions ORDER BY code ASC');
        $selectedRows = $this->db()->fetchAll(
            'SELECT permission_id FROM role_permissions WHERE role_id = :rid',
            ['rid' => (int) $id]
        );
        $selected = array_map(static fn (array $r) => (int) $r['permission_id'], $selectedRows);

        return $this->render('platform/roles/edit', [
            'title' => 'Edit role',
            'activeNav' => 'platform-roles',
            'navContext' => 'platform',
            'role' => $role,
            'permissions' => $permissions,
            'selected' => $selected,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            return $this->redirect('/platform/roles');
        }
        $roleId = (int) $id;
        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));
        if ($name === '') {
            Session::flash('error', 'Name is required.');
            return $this->redirect('/platform/roles/' . $roleId . '/edit');
        }
        $this->db()->exec(
            'UPDATE roles SET name = :name, description = :desc, updated_at = :ua
             WHERE id = :id AND stokvel_id IS NULL',
            [
                'name' => $name,
                'desc' => $description !== '' ? $description : null,
                'ua' => now_utc(),
                'id' => $roleId,
            ]
        );
        $this->syncPermissions($roleId, $request->input('permissions', []));
        Session::flash('success', 'Role updated.');
        return $this->redirect('/platform/roles/' . $roleId . '/edit');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            Session::flash('error', 'Only platform super admins can delete roles.');
            return $this->redirect('/platform/roles');
        }

        $roleId = (int) $id;
        $role = $this->db()->fetch(
            'SELECT * FROM roles WHERE id = :id AND stokvel_id IS NULL LIMIT 1',
            ['id' => $roleId]
        );
        if (!$role) {
            return $this->redirect('/platform/roles');
        }
        if (!empty($role['is_system'])) {
            Session::flash('error', 'System roles cannot be deleted.');
            return $this->redirect('/platform/roles/' . $roleId . '/edit');
        }

        $inUse = $this->db()->fetch(
            'SELECT COUNT(*) AS c FROM membership_roles WHERE role_id = :rid',
            ['rid' => $roleId]
        );
        if ((int) ($inUse['c'] ?? 0) > 0) {
            Session::flash('error', 'Role is assigned to members and cannot be deleted.');
            return $this->redirect('/platform/roles/' . $roleId . '/edit');
        }

        $this->db()->exec('DELETE FROM role_permissions WHERE role_id = :rid', ['rid' => $roleId]);
        $this->db()->exec('DELETE FROM roles WHERE id = :id AND stokvel_id IS NULL', ['id' => $roleId]);
        Session::flash('success', 'Role deleted.');
        return $this->redirect('/platform/roles');
    }

    /** @param mixed $permissionIds */
    private function syncPermissions(int $roleId, mixed $permissionIds): void
    {
        if (!is_array($permissionIds)) {
            $permissionIds = [];
        }
        $this->db()->exec('DELETE FROM role_permissions WHERE role_id = :rid', ['rid' => $roleId]);
        foreach ($permissionIds as $pid) {
            $pid = (int) $pid;
            if ($pid < 1) {
                continue;
            }
            $this->db()->insert(
                'INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:rid, :pid, :ca)',
                ['rid' => $roleId, 'pid' => $pid, 'ca' => now_utc()]
            );
        }
    }
}
