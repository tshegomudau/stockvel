<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Support\StokvelTypeCatalog;

final class StokvelTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }

        return $this->render('platform/stokvel-types/index', [
            'title' => 'Stokvel types',
            'activeNav' => 'platform-stokvel-types',
            'navContext' => 'platform',
            'types' => StokvelTypeCatalog::all($this->db()),
            'canManage' => $this->tenant()->isSuperAdmin(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            return $this->redirect('/platform/stokvel-types');
        }

        return $this->render('platform/stokvel-types/edit', [
            'title' => 'New stokvel type',
            'activeNav' => 'platform-stokvel-types',
            'navContext' => 'platform',
            'type' => null,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            return $this->redirect('/platform/stokvel-types');
        }

        $data = $this->validated($request, true);
        if ($data === null) {
            return $this->redirect('/platform/stokvel-types/create');
        }

        $exists = $this->db()->fetch(
            'SELECT id FROM stokvel_types WHERE code = :code LIMIT 1',
            ['code' => $data['code']]
        );
        if ($exists) {
            Session::flash('error', 'A type with that code already exists.');
            return $this->redirect('/platform/stokvel-types/create');
        }

        $now = now_utc();
        $id = (int) $this->db()->insert(
            'INSERT INTO stokvel_types (code, name, description, sort_order, is_active, created_at, updated_at)
             VALUES (:code, :name, :desc, :sort, :active, :ca, :ua)',
            [
                'code' => $data['code'],
                'name' => $data['name'],
                'desc' => $data['description'],
                'sort' => $data['sort_order'],
                'active' => $data['is_active'],
                'ca' => $now,
                'ua' => $now,
            ]
        );

        Session::flash('success', 'Stokvel type created.');
        return $this->redirect('/platform/stokvel-types/' . $id . '/edit');
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }

        $type = $this->db()->fetch('SELECT * FROM stokvel_types WHERE id = :id LIMIT 1', ['id' => (int) $id]);
        if (!$type) {
            return $this->redirect('/platform/stokvel-types');
        }

        return $this->render('platform/stokvel-types/edit', [
            'title' => 'Edit stokvel type',
            'activeNav' => 'platform-stokvel-types',
            'navContext' => 'platform',
            'type' => $type,
            'canManage' => $this->tenant()->isSuperAdmin(),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            return $this->redirect('/platform/stokvel-types');
        }

        $typeId = (int) $id;
        $type = $this->db()->fetch('SELECT * FROM stokvel_types WHERE id = :id LIMIT 1', ['id' => $typeId]);
        if (!$type) {
            return $this->redirect('/platform/stokvel-types');
        }

        $data = $this->validated($request, false);
        if ($data === null) {
            return $this->redirect('/platform/stokvel-types/' . $typeId . '/edit');
        }

        $this->db()->exec(
            'UPDATE stokvel_types
             SET name = :name, description = :desc, sort_order = :sort, is_active = :active, updated_at = :ua
             WHERE id = :id',
            [
                'name' => $data['name'],
                'desc' => $data['description'],
                'sort' => $data['sort_order'],
                'active' => $data['is_active'],
                'ua' => now_utc(),
                'id' => $typeId,
            ]
        );

        Session::flash('success', 'Stokvel type updated.');
        return $this->redirect('/platform/stokvel-types/' . $typeId . '/edit');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            Session::flash('error', 'Only platform super admins can delete types.');
            return $this->redirect('/platform/stokvel-types');
        }

        $typeId = (int) $id;
        $type = $this->db()->fetch('SELECT * FROM stokvel_types WHERE id = :id LIMIT 1', ['id' => $typeId]);
        if (!$type) {
            return $this->redirect('/platform/stokvel-types');
        }

        $inUse = $this->db()->fetch(
            'SELECT COUNT(*) AS c FROM stokvels WHERE stokvel_type = :code',
            ['code' => (string) $type['code']]
        );
        if ((int) ($inUse['c'] ?? 0) > 0) {
            Session::flash('error', 'Type is in use by one or more stokvels. Deactivate it instead of deleting.');
            return $this->redirect('/platform/stokvel-types/' . $typeId . '/edit');
        }

        $this->db()->exec('DELETE FROM stokvel_types WHERE id = :id', ['id' => $typeId]);
        Session::flash('success', 'Stokvel type deleted.');
        return $this->redirect('/platform/stokvel-types');
    }

    /** @return array{code: string, name: string, description: ?string, sort_order: int, is_active: int}|null */
    private function validated(Request $request, bool $requireCode): ?array
    {
        $code = strtolower(trim((string) $request->input('code', '')));
        $name = trim((string) $request->input('name', ''));
        $description = trim((string) $request->input('description', ''));
        $sortOrder = (int) $request->input('sort_order', 0);
        $isActive = $request->input('is_active') ? 1 : 0;

        $rules = ['name' => 'required|min:2'];
        $data = ['name' => $name];
        if ($requireCode) {
            $rules['code'] = 'required|min:2';
            $data['code'] = $code;
        }

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            Session::flash('error', $requireCode ? 'Code and name are required.' : 'Name is required.');
            return null;
        }

        if ($requireCode && !preg_match('/^[a-z0-9_]+$/', $code)) {
            Session::flash('error', 'Code must be lowercase letters, numbers, and underscores only.');
            return null;
        }

        return [
            'code' => $code,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'sort_order' => $sortOrder,
            'is_active' => $isActive,
        ];
    }
}
