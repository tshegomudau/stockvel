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
use App\Services\Loans\LoanProductService;
use App\Services\Loans\LoanScheduleCalculator;

final class LoanProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('loans.manage');
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

        $filterQuery = array_filter([
            'q' => $q !== '' ? $q : null,
        ], static fn ($v) => $v !== null);

        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM loan_products WHERE ' . $where,
            'SELECT * FROM loan_products WHERE ' . $where . ' ORDER BY name ASC',
            $params,
            $this->page($request),
            $this->perPage($request),
            url('/app/loans/products'),
            $filterQuery
        );

        /** @var LoanProductService $products */
        $products = app()->container()->get(LoanProductService::class);
        $items = array_map(
            static fn (array $row): array => $products->get($sid, (int) $row['id']) ?? $row,
            $paginator->items
        );
        $paginator = new Paginator(
            $items,
            $paginator->total,
            $paginator->page,
            $paginator->perPage,
            $paginator->path,
            $paginator->query
        );

        return $this->render('stokvel/loans/products/index', [
            'title' => 'Loan products',
            'activeNav' => 'loans',
            'navContext' => 'app',
            'paginator' => $paginator,
            'filterQ' => $q,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('loans.manage');

        return $this->render('stokvel/loans/products/create', [
            'title' => 'New loan product',
            'activeNav' => 'loans',
            'navContext' => 'app',
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();

        $data = [
            'name' => trim((string) $request->input('name', '')),
            'min_amount' => (string) $request->input('min_amount', ''),
            'max_amount' => (string) $request->input('max_amount', ''),
            'annual_interest_rate_percent' => (string) $request->input('annual_interest_rate_percent', $request->input('interest_rate_percent', '')),
            'min_term_months' => (int) $request->input('min_term_months', 1),
            'max_term_months' => (int) $request->input('max_term_months', 12),
            'amortization_method' => (string) $request->input('amortization_method', LoanScheduleCalculator::METHOD_REDUCING_BALANCE),
            'instalment_fee' => (string) $request->input('instalment_fee', '0'),
            'penalty_rate_percent' => (string) $request->input('penalty_rate_percent', '0'),
            'requires_guarantors' => $request->input('requires_guarantors') ? 1 : 0,
            'min_guarantors' => (int) $request->input('min_guarantors', 0),
        ];

        $validator = Validator::make($data, [
            'name' => 'required|string',
            'min_amount' => 'required|numeric',
            'max_amount' => 'required|numeric',
            'annual_interest_rate_percent' => 'required|numeric',
            'min_term_months' => 'required|integer',
            'max_term_months' => 'required|integer',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Invalid loan product details.');
            return $this->redirect('/app/loans/products/create');
        }

        try {
            /** @var LoanProductService $products */
            $products = app()->container()->get(LoanProductService::class);
            $products->create(
                $sid,
                $data['name'],
                $data['min_amount'],
                $data['max_amount'],
                $data['annual_interest_rate_percent'],
                $data['min_term_months'],
                $data['max_term_months'],
                $data['amortization_method'],
                $data['instalment_fee'],
                $data['penalty_rate_percent'],
                $data['requires_guarantors'] === 1,
                $data['min_guarantors']
            );
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/loans/products/create');
        }

        Session::flash('success', 'Loan product created.');
        return $this->redirect('/app/loans/products');
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();

        /** @var LoanProductService $products */
        $products = app()->container()->get(LoanProductService::class);
        $item = $products->get($sid, (int) $id);
        if ($item === null) {
            return $this->redirect('/app/loans/products');
        }

        return $this->render('stokvel/loans/products/edit', [
            'title' => 'Edit loan product',
            'activeNav' => 'loans',
            'navContext' => 'app',
            'item' => $item,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $productId = (int) $id;

        $fields = [
            'name' => trim((string) $request->input('name', '')),
            'min_amount' => (string) $request->input('min_amount', ''),
            'max_amount' => (string) $request->input('max_amount', ''),
            'annual_interest_rate_percent' => (string) $request->input('annual_interest_rate_percent', ''),
            'min_term_months' => (int) $request->input('min_term_months', 1),
            'max_term_months' => (int) $request->input('max_term_months', 12),
            'amortization_method' => (string) $request->input('amortization_method', LoanScheduleCalculator::METHOD_REDUCING_BALANCE),
            'instalment_fee' => (string) $request->input('instalment_fee', '0'),
            'penalty_rate_percent' => (string) $request->input('penalty_rate_percent', '0'),
            'requires_guarantors' => $request->input('requires_guarantors') ? 1 : 0,
            'min_guarantors' => (int) $request->input('min_guarantors', 0),
            'is_active' => $request->input('is_active') ? 1 : 0,
        ];

        $validator = Validator::make($fields, [
            'name' => 'required|string',
            'min_amount' => 'required|numeric',
            'max_amount' => 'required|numeric',
            'annual_interest_rate_percent' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Invalid loan product details.');
            return $this->redirect('/app/loans/products/' . $id . '/edit');
        }

        try {
            /** @var LoanProductService $products */
            $products = app()->container()->get(LoanProductService::class);
            $products->update($sid, $productId, $fields);
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/loans/products/' . $id . '/edit');
        }

        Session::flash('success', 'Loan product updated.');
        return $this->redirect('/app/loans/products');
    }
}
