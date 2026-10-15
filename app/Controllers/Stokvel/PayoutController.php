<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class PayoutController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM payouts WHERE stokvel_id = :sid',
            'SELECT * FROM payouts WHERE stokvel_id = :sid ORDER BY created_at DESC',
            ['sid' => $sid],
            $page,
            $perPage,
            url('/app/payouts')
        );
        return $this->render('stokvel/payouts/index', [
            'title' => 'Payouts',
            'activeNav' => 'payouts',
            'navContext' => 'app',
            'paginator' => $paginator,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('payouts.manage');
        return $this->render('stokvel/payouts/create', [
            'title' => 'New Payout',
            'activeNav' => 'payouts',
            'navContext' => 'app',
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $data = $request->all();
        unset($data['_token'], $data['_method']);
        $data['stokvel_id'] = $sid;
        $data['created_at'] = now_utc();
        $data['updated_at'] = now_utc();
        $this->service(\App\Services\Stokvel\PayoutService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function create(array $data): int
            {
                $cols = array_keys($data);
                $ph = array_map(static fn ($c) => ':' . $c, $cols);
                $sql = 'INSERT INTO payouts (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')';
                return (int) $this->db->insert($sql, $data);
            }
        })->create($data);
        Session::flash('success', 'Payout saved.');
        return $this->redirect('/app/payouts');
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch('SELECT * FROM payouts WHERE id = :id AND stokvel_id = :sid LIMIT 1', ['id' => (int) $id, 'sid' => $sid]);
        if (!$row) {
            return $this->redirect('/app/payouts');
        }
        return $this->render('stokvel/payouts/show', [
            'title' => 'Payout #' . $id,
            'activeNav' => 'payouts',
            'navContext' => 'app',
            'item' => $row,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch('SELECT * FROM payouts WHERE id = :id AND stokvel_id = :sid LIMIT 1', ['id' => (int) $id, 'sid' => $sid]);
        if (!$row) {
            return $this->redirect('/app/payouts');
        }
        return $this->render('stokvel/payouts/edit', [
            'title' => 'Edit Payout',
            'activeNav' => 'payouts',
            'navContext' => 'app',
            'item' => $row,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $data = $request->all();
        unset($data['_token'], $data['_method'], $data['stokvel_id']);
        $data['updated_at'] = now_utc();
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "{$col} = :{$col}";
        }
        $data['id'] = (int) $id;
        $data['sid'] = $sid;
        $this->db()->exec('UPDATE payouts SET ' . implode(', ', $sets) . ' WHERE id = :id AND stokvel_id = :sid', $data);
        Session::flash('success', 'Payout updated.');
        return $this->redirect('/app/payouts/' . $id);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec('UPDATE payouts SET deleted_at = :da, updated_at = :ua WHERE id = :id AND stokvel_id = :sid', [
            'da' => now_utc(),
            'ua' => now_utc(),
            'id' => (int) $id,
            'sid' => $sid,
        ]);
        Session::flash('success', 'Payout removed.');
        return $this->redirect('/app/payouts');
    }
}
