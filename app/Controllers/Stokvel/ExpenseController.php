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
use App\Services\Finance\ExpenseService;

final class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('expenses.manage');
        $sid = $this->requireStokvel();
        $page = $this->page($request);
        $perPage = $this->perPage($request);

        $status = trim((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));
        $categoryId = (int) $request->query('category_id', 0);
        $allowedStatuses = ['requested', 'approved', 'rejected', 'paid', 'reversed'];

        $where = 'e.stokvel_id = :sid';
        $params = ['sid' => $sid];
        if ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $where .= ' AND e.status = :status';
            $params['status'] = $status;
        }
        if ($categoryId > 0) {
            $where .= ' AND e.expense_category_id = :cat';
            $params['cat'] = $categoryId;
        }
        if ($q !== '') {
            $where .= ' AND (e.description LIKE :q_desc OR e.vendor_name LIKE :q_vendor OR e.expense_ref LIKE :q_ref)';
            $like = '%' . $q . '%';
            $params['q_desc'] = $like;
            $params['q_vendor'] = $like;
            $params['q_ref'] = $like;
        }

        $filterQuery = array_filter([
            'status' => $status !== '' && in_array($status, $allowedStatuses, true) ? $status : null,
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'q' => $q !== '' ? $q : null,
        ], static fn ($v) => $v !== null);

        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM expenses e WHERE ' . $where,
            'SELECT e.*, ec.name AS category_name,
                    TRIM(CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\'))) AS captured_by_name
             FROM expenses e
             LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id AND ec.stokvel_id = e.stokvel_id
             LEFT JOIN users u ON u.id = e.recorded_by_user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE ' . $where . ' ORDER BY e.created_at DESC',
            $params,
            $page,
            $perPage,
            url('/app/expenses'),
            $filterQuery
        );

        $categories = $this->db()->fetchAll(
            'SELECT id, name FROM expense_categories WHERE stokvel_id = :sid AND is_active = 1 ORDER BY name ASC',
            ['sid' => $sid]
        );

        return $this->render('stokvel/expenses/index', [
            'title' => 'Expenses',
            'activeNav' => 'expenses',
            'navContext' => 'app',
            'paginator' => $paginator,
            'filterStatus' => $status !== '' && in_array($status, $allowedStatuses, true) ? $status : '',
            'filterQ' => $q,
            'filterCategoryId' => $categoryId,
            'filterCategories' => $categories,
            'statusOptions' => $allowedStatuses,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('expenses.manage');
        $sid = $this->requireStokvel();
        /** @var ExpenseService $expenses */
        $expenses = app()->container()->get(ExpenseService::class);
        return $this->render('stokvel/expenses/create', [
            'title' => 'New Expense',
            'activeNav' => 'expenses',
            'navContext' => 'app',
            'categories' => $expenses->listCategories($sid),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('expenses.manage');
        $sid = $this->requireStokvel();
        $data = [
            'category_id' => (int) $request->input('category_id', $request->input('expense_category_id', 0)),
            'description' => trim((string) $request->input('description', '')),
            'amount' => (string) $request->input('amount', ''),
            'expense_date' => (string) $request->input('expense_date', $request->input('incurred_at', date('Y-m-d'))),
            'vendor_name' => trim((string) $request->input('vendor_name', $request->input('category', ''))),
        ];
        $validator = Validator::make($data, [
            'category_id' => 'required|integer',
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'expense_date' => 'required|date',
        ]);
        if ($validator->fails() || $data['category_id'] < 1) {
            $this->flashOld($request);
            Session::flash('error', 'Invalid expense details.');
            return $this->redirect('/app/expenses/create');
        }

        try {
            /** @var ExpenseService $expenses */
            $expenses = app()->container()->get(ExpenseService::class);
            $expenses->requestExpense(
                $sid,
                $data['category_id'],
                $data['amount'],
                $data['description'],
                (int) $this->userId(),
                $data['expense_date'],
                $data['vendor_name'] !== '' ? $data['vendor_name'] : null
            );
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/expenses/create');
        }

        Session::flash('success', 'Expense saved.');
        return $this->redirect('/app/expenses');
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('expenses.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch(
            'SELECT e.*, ec.name AS category_name, ec.code AS category_code,
                    TRIM(CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\'))) AS captured_by_name
             FROM expenses e
             LEFT JOIN expense_categories ec ON ec.id = e.expense_category_id AND ec.stokvel_id = e.stokvel_id
             LEFT JOIN users u ON u.id = e.recorded_by_user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE e.id = :id AND e.stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$row) {
            return $this->redirect('/app/expenses');
        }
        return $this->render('stokvel/expenses/show', [
            'title' => 'Expense #' . $id,
            'activeNav' => 'expenses',
            'navContext' => 'app',
            'item' => $row,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('expenses.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch('SELECT * FROM expenses WHERE id = :id AND stokvel_id = :sid LIMIT 1', ['id' => (int) $id, 'sid' => $sid]);
        if (!$row) {
            return $this->redirect('/app/expenses');
        }
        /** @var ExpenseService $expenses */
        $expenses = app()->container()->get(ExpenseService::class);
        return $this->render('stokvel/expenses/edit', [
            'title' => 'Edit Expense',
            'activeNav' => 'expenses',
            'navContext' => 'app',
            'item' => $row,
            'categories' => $expenses->listCategories($sid),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('expenses.manage');
        $sid = $this->requireStokvel();
        $description = trim((string) $request->input('description', ''));
        $amount = (string) $request->input('amount', '');
        if ($description === '' || $amount === '') {
            Session::flash('error', 'Description and amount are required.');
            return $this->redirect('/app/expenses/' . $id . '/edit');
        }
        $this->db()->exec(
            'UPDATE expenses SET description = :description, amount = :amount, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid AND status IN (\'draft\', \'requested\')',
            [
                'description' => $description,
                'amount' => $amount,
                'ua' => now_utc(),
                'id' => (int) $id,
                'sid' => $sid,
            ]
        );
        Session::flash('success', 'Expense updated.');
        return $this->redirect('/app/expenses/' . $id);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('expenses.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE expenses SET status = :status, updated_at = :ua WHERE id = :id AND stokvel_id = :sid AND status IN (\'draft\', \'requested\')',
            [
                'status' => 'rejected',
                'ua' => now_utc(),
                'id' => (int) $id,
                'sid' => $sid,
            ]
        );
        Session::flash('success', 'Expense cancelled.');
        return $this->redirect('/app/expenses');
    }
}
