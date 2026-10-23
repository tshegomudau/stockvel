<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Exceptions\FinanceException;
use App\Services\Finance\FeeStructureService;

final class FeeStructureController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('fees.manage');
        $sid = $this->requireStokvel();
        $page = $this->page($request);
        $perPage = $this->perPage($request);

        $status = trim((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));
        $allowedStatuses = ['active', 'inactive'];

        $where = 'stokvel_id = :sid';
        $params = ['sid' => $sid];
        if ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $where .= ' AND status = :status';
            $params['status'] = $status;
        }
        if ($q !== '') {
            $where .= ' AND (name LIKE :q_name OR code LIKE :q_code)';
            $like = '%' . $q . '%';
            $params['q_name'] = $like;
            $params['q_code'] = $like;
        }

        $filterQuery = array_filter([
            'status' => $status !== '' && in_array($status, $allowedStatuses, true) ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], static fn ($v) => $v !== null);

        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM fee_structures WHERE ' . $where,
            'SELECT * FROM fee_structures WHERE ' . $where . ' ORDER BY created_at DESC',
            $params,
            $page,
            $perPage,
            url('/app/fees'),
            $filterQuery
        );
        return $this->render('stokvel/fees/index', [
            'title' => 'Fee structures',
            'activeNav' => 'fees',
            'navContext' => 'app',
            'paginator' => $paginator,
            'filterStatus' => $status !== '' && in_array($status, $allowedStatuses, true) ? $status : '',
            'filterQ' => $q,
            'statusOptions' => $allowedStatuses,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('fees.manage');
        $sid = $this->requireStokvel();
        return $this->render('stokvel/fees/create', [
            'title' => 'New Fee structure',
            'activeNav' => 'fees',
            'navContext' => 'app',
            'feeCategories' => $this->activeFeeCategories($sid),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('fees.manage');
        $sid = $this->requireStokvel();
        $name = trim((string) $request->input('name', ''));
        $amount = (string) $request->input('amount', '');
        $frequency = (string) $request->input('frequency', 'monthly');
        $categoryCode = strtolower(trim((string) $request->input('category', 'monthly')));
        $allowedCodes = array_column($this->activeFeeCategories($sid), 'code');
        if ($allowedCodes !== [] && !in_array($categoryCode, $allowedCodes, true)) {
            $categoryCode = $allowedCodes[0];
        }
        if ($allowedCodes === [] && $categoryCode === '') {
            $categoryCode = 'contribution';
        }
        $structureType = $categoryCode !== '' ? $categoryCode : 'contribution';
        $validator = Validator::make(
            ['name' => $name, 'amount' => $amount, 'frequency' => $frequency],
            ['name' => 'required|string', 'amount' => 'required|numeric', 'frequency' => 'required|string']
        );
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Invalid fee structure details.');
            return $this->redirect('/app/fees/create');
        }

        try {
            /** @var FeeStructureService $fees */
            $fees = $this->service(FeeStructureService::class, fn ($db) => new FeeStructureService($db));
            $fees->createStructure(
                $sid,
                $name,
                $structureType,
                $amount,
                $frequency
            );
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/fees/create');
        }

        Session::flash('success', 'Fee structure saved.');
        return $this->redirect('/app/fees');
    }

    /** @return list<array<string, mixed>> */
    private function activeFeeCategories(int $stokvelId): array
    {
        try {
            return $this->db()->fetchAll(
                'SELECT code, name FROM fee_categories
                 WHERE stokvel_id = :sid AND is_active = 1
                 ORDER BY name ASC',
                ['sid' => $stokvelId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('fees.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch('SELECT * FROM fee_structures WHERE id = :id AND stokvel_id = :sid LIMIT 1', ['id' => (int) $id, 'sid' => $sid]);
        if (!$row) {
            return $this->redirect('/app/fees');
        }
        return $this->render('stokvel/fees/show', [
            'title' => 'Fee structure #' . $id,
            'activeNav' => 'fees',
            'navContext' => 'app',
            'item' => $row,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('fees.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch('SELECT * FROM fee_structures WHERE id = :id AND stokvel_id = :sid LIMIT 1', ['id' => (int) $id, 'sid' => $sid]);
        if (!$row) {
            return $this->redirect('/app/fees');
        }
        return $this->render('stokvel/fees/edit', [
            'title' => 'Edit Fee structure',
            'activeNav' => 'fees',
            'navContext' => 'app',
            'item' => $row,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('fees.manage');
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
        $this->db()->exec('UPDATE fee_structures SET ' . implode(', ', $sets) . ' WHERE id = :id AND stokvel_id = :sid', $data);
        Session::flash('success', 'Fee structure updated.');
        return $this->redirect('/app/fees/' . $id);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('fees.manage');
        $sid = $this->requireStokvel();
        /** @var FeeStructureService $fees */
        $fees = $this->service(FeeStructureService::class, fn ($db) => new FeeStructureService($db));
        try {
            $fees->deactivate($sid, (int) $id);
        } catch (FinanceException) {
            Session::flash('error', 'Fee structure not found.');
            return $this->redirect('/app/fees');
        }
        Session::flash('success', 'Fee structure removed.');
        return $this->redirect('/app/fees');
    }
}
