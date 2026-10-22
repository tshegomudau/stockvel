<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Exceptions\FinanceException;
use App\Services\Loans\LoanProductService;
use App\Services\Loans\LoanService;

final class LoanController extends Controller
{
    public function applyForm(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $membership = $this->activeMembership($sid);
        if ($membership === null) {
            Session::flash('error', 'You must be an active member to apply for a loan.');
            return $this->redirect('/member');
        }

        /** @var LoanProductService $products */
        $products = app()->container()->get(LoanProductService::class);
        $guarantors = $this->db()->fetchAll(
            "SELECT sm.id, p.first_name, p.last_name, sm.member_number
             FROM memberships sm
             JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE sm.stokvel_id = :sid AND sm.status = 'active' AND sm.id <> :mid
             ORDER BY p.last_name, p.first_name",
            ['sid' => $sid, 'mid' => (int) $membership['id']]
        );

        return $this->render('member/loans/apply', [
            'title' => 'Apply for a loan',
            'activeNav' => 'member-loans',
            'navContext' => 'member',
            'membership' => $membership,
            'products' => $products->listActive($sid),
            'guarantors' => $guarantors,
        ]);
    }

    public function applyStore(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();
        $membership = $this->activeMembership($sid);
        if ($membership === null) {
            Session::flash('error', 'You must be an active member to apply for a loan.');
            return $this->redirect('/member');
        }

        $data = [
            'loan_product_id' => (int) $request->input('loan_product_id', 0),
            'principal' => (string) $request->input('principal', $request->input('amount', '')),
            'term_months' => (int) $request->input('term_months', 6),
            'purpose' => trim((string) $request->input('purpose', '')),
        ];
        $validator = Validator::make($data, [
            'loan_product_id' => 'required|integer',
            'principal' => 'required|numeric',
            'term_months' => 'required|integer',
        ]);
        if ($validator->fails() || $data['loan_product_id'] < 1) {
            $this->flashOld($request);
            Session::flash('error', 'Invalid loan application details.');
            return $this->redirect('/member/loans/apply');
        }

        $guarantorIds = $request->input('guarantor_ids', []);
        if (!is_array($guarantorIds)) {
            $guarantorIds = [];
        }
        $guarantorIds = array_values(array_unique(array_filter(array_map('intval', $guarantorIds))));
        $guarantorIds = array_values(array_filter(
            $guarantorIds,
            static fn (int $id): bool => $id > 0 && $id !== (int) $membership['id']
        ));

        try {
            /** @var LoanService $loans */
            $loans = app()->container()->get(LoanService::class);
            $applicationId = $loans->apply(
                $sid,
                (int) $membership['id'],
                $data['loan_product_id'],
                $data['principal'],
                $data['term_months'],
                $userId,
                $data['purpose'],
                $guarantorIds !== [] ? $guarantorIds : null
            );
            $loans->createPendingApprovalRequest($sid, $applicationId, $userId);
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/member/loans/apply');
        }

        Session::flash('success', 'Your loan application has been submitted for committee review.');
        return $this->redirect('/member');
    }

    /** @return array<string, mixed>|null */
    private function activeMembership(int $stokvelId): ?array
    {
        $row = $this->db()->fetch(
            'SELECT * FROM memberships WHERE stokvel_id = :sid AND user_id = :uid AND status = :st LIMIT 1',
            ['sid' => $stokvelId, 'uid' => (int) $this->userId(), 'st' => 'active']
        );

        return $row !== null ? $row : null;
    }
}
