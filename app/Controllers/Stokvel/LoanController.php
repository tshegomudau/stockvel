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

        $status = trim((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));
        $allowedStatuses = ['submitted', 'approved', 'rejected', 'cancelled', 'disbursed'];

        $where = 'la.stokvel_id = :sid';
        $params = ['sid' => $sid];
        if ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $where .= ' AND la.status = :status';
            $params['status'] = $status;
        }
        if ($q !== '') {
            $where .= ' AND (p.first_name LIKE :q_fn OR p.last_name LIKE :q_ln
                OR CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\')) LIKE :q_full
                OR la.application_ref LIKE :q_ref OR lp.name LIKE :q_product)';
            $like = '%' . $q . '%';
            $params['q_fn'] = $like;
            $params['q_ln'] = $like;
            $params['q_full'] = $like;
            $params['q_ref'] = $like;
            $params['q_product'] = $like;
        }

        $filterQuery = array_filter([
            'status' => $status !== '' && in_array($status, $allowedStatuses, true) ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], static fn ($v) => $v !== null);

        $joins = 'LEFT JOIN memberships sm ON sm.id = la.membership_id
             LEFT JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             LEFT JOIN loan_products lp ON lp.id = la.loan_product_id';

        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM loan_applications la ' . $joins . ' WHERE ' . $where,
            'SELECT la.*, la.application_ref, la.requested_amount, la.membership_id AS member_id,
                    p.first_name, p.last_name, lp.name AS product_name
             FROM loan_applications la
             ' . $joins . '
             WHERE ' . $where . '
             ORDER BY la.created_at DESC',
            $params,
            $page,
            $perPage,
            url('/app/loans'),
            $filterQuery
        );
        return $this->render('stokvel/loans/index', [
            'title' => 'Loans',
            'activeNav' => 'loans',
            'navContext' => 'app',
            'paginator' => $paginator,
            'filterStatus' => $status !== '' && in_array($status, $allowedStatuses, true) ? $status : '',
            'filterQ' => $q,
            'statusOptions' => $allowedStatuses,
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
        $userId = (int) $this->userId();
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
            $applicationId = $loans->apply(
                $sid,
                $data['member_id'],
                $data['loan_product_id'],
                $data['principal'],
                $data['term_months'],
                $userId,
                $data['purpose']
            );
            $loans->createPendingApprovalRequest($sid, $applicationId, $userId);
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
        $applicationId = (int) $id;

        $item = $this->db()->fetch(
            'SELECT la.*, la.membership_id AS member_id, la.requested_amount,
                    p.first_name, p.last_name, lp.name AS product_name, lp.code AS product_code
             FROM loan_applications la
             LEFT JOIN memberships sm ON sm.id = la.membership_id
             LEFT JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             LEFT JOIN loan_products lp ON lp.id = la.loan_product_id
             WHERE la.id = :id AND la.stokvel_id = :sid LIMIT 1',
            ['id' => $applicationId, 'sid' => $sid]
        );
        $loanRecord = null;
        if (!$item) {
            $loanRecord = $this->db()->fetch(
                'SELECT l.*, l.membership_id AS member_id, l.principal_amount AS requested_amount,
                        p.first_name, p.last_name, lp.name AS product_name
                 FROM loans l
                 LEFT JOIN memberships sm ON sm.id = l.membership_id
                 LEFT JOIN users u ON u.id = sm.user_id
                 LEFT JOIN user_profiles p ON p.user_id = u.id
                 LEFT JOIN loan_products lp ON lp.id = l.loan_product_id
                 WHERE l.id = :id AND l.stokvel_id = :sid LIMIT 1',
                ['id' => $applicationId, 'sid' => $sid]
            );
            if (!$loanRecord) {
                return $this->redirect('/app/loans');
            }
            $item = $loanRecord;
        } else {
            $loanRecord = $this->db()->fetch(
                'SELECT * FROM loans WHERE loan_application_id = :aid AND stokvel_id = :sid LIMIT 1',
                ['aid' => $applicationId, 'sid' => $sid]
            );
        }

        $approvalRequest = $this->db()->fetch(
            "SELECT * FROM approval_requests
             WHERE stokvel_id = :sid AND reference_type = 'loan_application' AND reference_id = :ref
             ORDER BY id DESC LIMIT 1",
            ['sid' => $sid, 'ref' => $applicationId]
        );

        return $this->render('stokvel/loans/show', [
            'title' => 'Loan application',
            'activeNav' => 'loans',
            'navContext' => 'app',
            'item' => $item,
            'loanRecord' => $loanRecord,
            'approvalRequest' => $approvalRequest,
            'regulatoryNotice' => LoanService::REGULATORY_WARNING,
        ]);
    }

    public function approve(Request $request, string $id): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();
        $applicationId = (int) $id;
        $notes = trim((string) $request->input('notes', ''));
        $approvedAmount = trim((string) $request->input('approved_amount', ''));

        try {
            /** @var LoanService $loans */
            $loans = app()->container()->get(LoanService::class);
            $loans->committeeApprove(
                $sid,
                $applicationId,
                $userId,
                $approvedAmount !== '' ? $approvedAmount : null,
                $notes !== '' ? $notes : null
            );
            $loans->finalizeApprovalRequest($sid, $applicationId, $userId, 'approved', $notes);
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/loans/' . $id);
        }

        Session::flash('success', 'Loan application approved.');
        return $this->redirect('/app/loans/' . $id);
    }

    public function reject(Request $request, string $id): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();
        $applicationId = (int) $id;
        $reason = trim((string) $request->input('notes', $request->input('reason', '')));
        if ($reason === '') {
            Session::flash('error', 'A rejection reason is required.');
            return $this->redirect('/app/loans/' . $id);
        }

        try {
            /** @var LoanService $loans */
            $loans = app()->container()->get(LoanService::class);
            $loans->committeeReject($sid, $applicationId, $userId, $reason);
            $loans->finalizeApprovalRequest($sid, $applicationId, $userId, 'rejected', $reason);
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/loans/' . $id);
        }

        Session::flash('success', 'Loan application rejected.');
        return $this->redirect('/app/loans/' . $id);
    }

    public function disburse(Request $request, string $id): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();
        $applicationId = (int) $id;

        try {
            app()->container()->get(\App\Services\Finance\ChartOfAccountsService::class)->ensureDefaultAccounts($sid);
            /** @var LoanService $loans */
            $loans = app()->container()->get(LoanService::class);
            $loans->disburse($sid, $applicationId, $userId);
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/loans/' . $id);
        }

        Session::flash('success', 'Loan disbursed.');
        return $this->redirect('/app/loans/' . $id);
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
