<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class LoanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM loans WHERE stokvel_id = :sid',
            'SELECT * FROM loans WHERE stokvel_id = :sid ORDER BY created_at DESC',
            ['sid' => $sid],
            $page,
            $perPage,
            url('/app/loans')
        );
        return $this->render('stokvel/loans/index', [
            'title' => 'Loans',
            'activeNav' => 'loans',
            'navContext' => 'app',
            'paginator' => $paginator,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('loans.manage');
        return $this->render('stokvel/loans/create', [
            'title' => 'New Loan',
            'activeNav' => 'loans',
            'navContext' => 'app',
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $data = $request->all();
        unset($data['_token'], $data['_method']);
        $data['stokvel_id'] = $sid;
        $data['created_at'] = now_utc();
        $data['updated_at'] = now_utc();
        $this->service(\App\Services\Stokvel\LoanService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function create(array $data): int
            {
                $cols = array_keys($data);
                $ph = array_map(static fn ($c) => ':' . $c, $cols);
                $sql = 'INSERT INTO loans (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')';
                return (int) $this->db->insert($sql, $data);
            }
        })->create($data);
        Session::flash('success', 'Loan saved.');
        return $this->redirect('/app/loans');
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch('SELECT * FROM loans WHERE id = :id AND stokvel_id = :sid LIMIT 1', ['id' => (int) $id, 'sid' => $sid]);
        if (!$row) {
            return $this->redirect('/app/loans');
        }
        return $this->render('stokvel/loans/show', [
            'title' => 'Loan #' . $id,
            'activeNav' => 'loans',
            'navContext' => 'app',
            'item' => $row,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch('SELECT * FROM loans WHERE id = :id AND stokvel_id = :sid LIMIT 1', ['id' => (int) $id, 'sid' => $sid]);
        if (!$row) {
            return $this->redirect('/app/loans');
        }
        return $this->render('stokvel/loans/edit', [
            'title' => 'Edit Loan',
            'activeNav' => 'loans',
            'navContext' => 'app',
            'item' => $row,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('loans.manage');
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
        $this->db()->exec('UPDATE loans SET ' . implode(', ', $sets) . ' WHERE id = :id AND stokvel_id = :sid', $data);
        Session::flash('success', 'Loan updated.');
        return $this->redirect('/app/loans/' . $id);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec('UPDATE loans SET deleted_at = :da, updated_at = :ua WHERE id = :id AND stokvel_id = :sid', [
            'da' => now_utc(),
            'ua' => now_utc(),
            'id' => (int) $id,
            'sid' => $sid,
        ]);
        Session::flash('success', 'Loan removed.');
        return $this->redirect('/app/loans');
    }
}
