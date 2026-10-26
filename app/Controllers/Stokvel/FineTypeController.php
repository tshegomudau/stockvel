<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Support\MoneyMath;

final class FineTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        $q = trim((string) $request->query('q', ''));
        $where = 'stokvel_id = :sid';
        $params = ['sid' => $sid];
        if ($q !== '') {
            $where .= ' AND (name LIKE :q_name OR code LIKE :q_code)';
            $like = '%' . $q . '%';
            $params['q_name'] = $like;
            $params['q_code'] = $like;
        }
        $types = $this->db()->fetchAll(
            'SELECT * FROM fine_types WHERE ' . $where . ' ORDER BY display_order ASC, name ASC',
            $params
        );
        return $this->render('stokvel/fine-types/index', [
            'title' => 'Fine types',
            'activeNav' => 'fines',
            'navContext' => 'app',
            'types' => $types,
            'filterQ' => $q,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        $data = [
            'code' => strtolower(preg_replace('/[^a-z0-9_]+/i', '_', trim((string) $request->input('code', ''))) ?? ''),
            'name' => trim((string) $request->input('name', '')),
            'default_amount' => (string) $request->input('default_amount', '0'),
            'description' => trim((string) $request->input('description', '')),
        ];
        $validator = Validator::make($data, [
            'code' => 'required|min:2',
            'name' => 'required|min:2',
        ]);
        if ($validator->fails()) {
            Session::flash('error', 'Fine type code and name are required.');
            return $this->redirect('/app/fine-types');
        }

        $amount = '0.00';
        try {
            $amount = MoneyMath::normalize($data['default_amount'] !== '' ? $data['default_amount'] : '0');
        } catch (\Throwable) {
            Session::flash('error', 'Invalid default amount.');
            return $this->redirect('/app/fine-types');
        }

        $now = now_utc();
        $order = (int) ($this->db()->fetch(
            'SELECT COALESCE(MAX(display_order), 0) AS m FROM fine_types WHERE stokvel_id = :sid',
            ['sid' => $sid]
        )['m'] ?? 0) + 1;

        try {
            $this->db()->insert(
                'INSERT INTO fine_types
                    (stokvel_id, code, name, description, default_amount, display_order, is_active, created_at, updated_at)
                 VALUES
                    (:sid, :code, :name, :desc, :amt, :ord, 1, :ca, :ua)',
                [
                    'sid' => $sid,
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'desc' => $data['description'] !== '' ? $data['description'] : null,
                    'amt' => $amount,
                    'ord' => $order,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
            Session::flash('success', 'Fine type created.');
        } catch (\Throwable) {
            Session::flash('error', 'Could not create fine type (duplicate code?).');
        }
        return $this->redirect('/app/fine-types');
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        $name = trim((string) $request->input('name', ''));
        $defaultAmount = (string) $request->input('default_amount', '');
        if ($name === '') {
            Session::flash('error', 'Name is required.');
            return $this->redirect('/app/fine-types');
        }
        $params = [
            'name' => $name,
            'ua' => now_utc(),
            'id' => (int) $id,
            'sid' => $sid,
        ];
        $set = 'name = :name, updated_at = :ua';
        if ($defaultAmount !== '') {
            try {
                $params['amt'] = MoneyMath::normalize($defaultAmount);
                $set .= ', default_amount = :amt';
            } catch (\Throwable) {
                Session::flash('error', 'Invalid default amount.');
                return $this->redirect('/app/fine-types');
            }
        }
        $this->db()->exec(
            'UPDATE fine_types SET ' . $set . ' WHERE id = :id AND stokvel_id = :sid',
            $params
        );
        Session::flash('success', 'Fine type updated.');
        return $this->redirect('/app/fine-types');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        $inUse = $this->db()->fetch(
            'SELECT COUNT(*) AS c FROM fines WHERE stokvel_id = :sid AND fine_type_id = :id',
            ['sid' => $sid, 'id' => (int) $id]
        );
        if ((int) ($inUse['c'] ?? 0) > 0) {
            $this->db()->exec(
                'UPDATE fine_types SET is_active = 0, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
                ['ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
            );
            Session::flash('success', 'Fine type is in use — deactivated instead of deleted.');
            return $this->redirect('/app/fine-types');
        }
        $this->db()->exec(
            'DELETE FROM fine_types WHERE id = :id AND stokvel_id = :sid',
            ['id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Fine type deleted.');
        return $this->redirect('/app/fine-types');
    }

    public function activate(Request $request, string $id): Response
    {
        return $this->setActive($id, true);
    }

    public function deactivate(Request $request, string $id): Response
    {
        return $this->setActive($id, false);
    }

    private function setActive(string $id, bool $active): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE fine_types SET is_active = :active, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['active' => $active ? 1 : 0, 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', $active ? 'Fine type reactivated.' : 'Fine type deactivated.');
        return $this->redirect('/app/fine-types');
    }
}
