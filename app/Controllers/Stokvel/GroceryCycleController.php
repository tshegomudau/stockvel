<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class GroceryCycleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('grocery.manage');
        $sid = $this->requireStokvel();
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM grocery_cycles WHERE stokvel_id = :sid',
            'SELECT * FROM grocery_cycles WHERE stokvel_id = :sid ORDER BY created_at DESC',
            ['sid' => $sid],
            $this->page($request),
            $this->perPage($request),
            url('/app/grocery')
        );
        return $this->render('stokvel/grocery/index', [
            'title' => 'Grocery cycles',
            'activeNav' => 'grocery',
            'navContext' => 'app',
            'paginator' => $paginator,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('grocery.manage');
        return $this->render('stokvel/grocery/create', [
            'title' => 'New grocery cycle',
            'activeNav' => 'grocery',
            'navContext' => 'app',
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('grocery.manage');
        $sid = $this->requireStokvel();
        $data = [
            'title' => trim((string) $request->input('title', '')),
            'target_amount' => (string) $request->input('target_amount', ''),
            'distribution_date' => (string) $request->input('distribution_date', ''),
            'notes' => trim((string) $request->input('notes', '')),
        ];
        $validator = Validator::make($data, ['title' => 'required|min:2']);
        if ($validator->fails()) {
            Session::flash('error', 'Title is required.');
            return $this->redirect('/app/grocery/create');
        }

        $now = now_utc();
        $id = (int) $this->db()->insert(
            'INSERT INTO grocery_cycles
                (stokvel_id, cycle_ref, title, target_amount, distribution_date, status, notes,
                 created_by_user_id, created_at, updated_at)
             VALUES
                (:sid, :ref, :title, :target, :dd, :status, :notes, :by, :ca, :ua)',
            [
                'sid' => $sid,
                'ref' => 'GRC-' . $sid . '-' . gmdate('YmdHis'),
                'title' => $data['title'],
                'target' => $data['target_amount'] !== '' ? $data['target_amount'] : null,
                'dd' => $data['distribution_date'] !== '' ? $data['distribution_date'] : null,
                'status' => 'open',
                'notes' => $data['notes'] !== '' ? $data['notes'] : null,
                'by' => $this->userId(),
                'ca' => $now,
                'ua' => $now,
            ]
        );

        $items = $request->input('items', []);
        if (is_array($items)) {
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = trim((string) ($item['item_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $this->db()->insert(
                    'INSERT INTO grocery_cycle_items
                        (stokvel_id, grocery_cycle_id, item_name, quantity, unit, estimated_cost, created_at, updated_at)
                     VALUES (:sid, :cid, :name, :qty, :unit, :cost, :ca, :ua)',
                    [
                        'sid' => $sid,
                        'cid' => $id,
                        'name' => $name,
                        'qty' => (string) ($item['quantity'] ?? '1'),
                        'unit' => trim((string) ($item['unit'] ?? '')) ?: null,
                        'cost' => ($item['estimated_cost'] ?? '') !== '' ? (string) $item['estimated_cost'] : null,
                        'ca' => $now,
                        'ua' => $now,
                    ]
                );
            }
        }

        Session::flash('success', 'Grocery cycle created.');
        return $this->redirect('/app/grocery/' . $id);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('grocery.manage');
        $sid = $this->requireStokvel();
        $cycle = $this->db()->fetch(
            'SELECT * FROM grocery_cycles WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$cycle) {
            return $this->redirect('/app/grocery');
        }
        $items = $this->db()->fetchAll(
            'SELECT * FROM grocery_cycle_items WHERE grocery_cycle_id = :id AND stokvel_id = :sid ORDER BY id ASC',
            ['id' => (int) $id, 'sid' => $sid]
        );
        return $this->render('stokvel/grocery/show', [
            'title' => (string) $cycle['title'],
            'activeNav' => 'grocery',
            'navContext' => 'app',
            'cycle' => $cycle,
            'items' => $items,
        ]);
    }
}
