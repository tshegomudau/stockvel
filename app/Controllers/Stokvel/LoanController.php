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
use App\Services\Loans\LoanService;

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
            'SELECT COUNT(*) AS c FROM loan_applications WHERE stokvel_id = :sid',
            'SELECT * FROM loan_applications WHERE stokvel_id = :sid ORDER BY created_at DESC',
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
        $sid = $this->requireStokvel();
        /** @var LoanProductService $products */
        $products = app()->container()->get(LoanProductService::class);
        $members = $this->db()->fetchAll(
            'SELECT sm.id, p.first_name, p.last_name FROM memberships sm
             JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE sm.stokvel_id = :sid AND sm.status = :st
             ORDER BY p.last_name, p.first_name',
            ['sid' => $sid, 'st' => 'active']
        );
        return $this->render('stokvel/loans/create', [
            'title' => 'New Loan',
            'activeNav' => 'loans',
            'navContext' => 'app',
            'members' => $members,
            'products' => $products->listActive($sid),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $data = [
            'member_id' => (int) $request->input('member_id', 0),
            'loan_product_id' => (int) $request->input('loan_product_id', 0),
            'principal' => (string) $request->input('principal', $request->input('amount', '')),
            'term_months' => (int) $request->input('term_months', 6),
            'purpose' => trim((string) $request->input('purpose', '')),
        ];
        $validator = Validator::make($data, [
            'member_id' => 'required|integer',
            'loan_product_id' => 'required|integer',
            'principal' => 'required|numeric',
            'term_months' => 'required|integer',
        ]);
        if ($validator->fails() || $data['member_id'] < 1 || $data['loan_product_id'] < 1) {
            $this->flashOld($request);
            Session::flash('error', 'Invalid loan application details.');
            return $this->redirect('/app/loans/create');
        }

        try {
            /** @var LoanService $loans */
            $loans = app()->container()->get(LoanService::class);
            $loans->apply(
                $sid,
                $data['member_id'],
                $data['loan_product_id'],
                $data['principal'],
                $data['term_months'],
                (int) $this->userId(),
                $data['purpose']
            );
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/loans/create');
        }

        Session::flash('success', 'Loan application submitted.');
        return $this->redirect('/app/loans');
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch(
            'SELECT * FROM loan_applications WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$row) {
            $row = $this->db()->fetch(
                'SELECT * FROM loans WHERE id = :id AND stokvel_id = :sid LIMIT 1',
                ['id' => (int) $id, 'sid' => $sid]
            );
        }
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
        $row = $this->db()->fetch(
            'SELECT * FROM loan_applications WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
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
        $purpose = trim((string) $request->input('purpose', ''));
        $this->db()->exec(
            'UPDATE loan_applications SET purpose = :purpose, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid AND status = :status',
            [
                'purpose' => $purpose !== '' ? $purpose : null,
                'ua' => now_utc(),
                'id' => (int) $id,
                'sid' => $sid,
                'status' => 'submitted',
            ]
        );
        Session::flash('success', 'Loan application updated.');
        return $this->redirect('/app/loans/' . $id);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE loan_applications SET status = :status, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid AND status IN (\'submitted\', \'approved\')',
            ['status' => 'cancelled', 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Loan application cancelled.');
        return $this->redirect('/app/loans');
    }
}
