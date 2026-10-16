<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('members.view');
        $sid = $this->requireStokvel();
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $status = (string) $request->query('status', '');

        $params = ['sid' => $sid];
        $where = 'sm.stokvel_id = :sid';
        if ($status !== '') {
            $where .= ' AND sm.status = :status';
            $params['status'] = $status;
        }

        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM memberships sm WHERE {$where}",
            "SELECT sm.*, p.first_name, p.last_name, u.email, u.mobile FROM memberships sm JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE {$where} ORDER BY sm.created_at DESC",
            $params,
            $page,
            $perPage,
            url('/app/members'),
            array_filter(['status' => $status])
        );

        return $this->render('stokvel/members/index', [
            'title' => 'Members',
            'activeNav' => 'members',
            'navContext' => 'app',
            'paginator' => $paginator,
            'statusFilter' => $status,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('members.view');
        $sid = $this->requireStokvel();
        $member = $this->db()->fetch(
            'SELECT sm.*, p.first_name, p.last_name, u.email, u.mobile FROM memberships sm JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE sm.id = :id AND sm.stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$member) {
            return $this->redirect('/app/members');
        }

        return $this->render('stokvel/members/show', [
            'title' => trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')),
            'activeNav' => 'members',
            'navContext' => 'app',
            'member' => $member,
        ]);
    }

    public function inviteForm(Request $request): Response
    {
        $this->requirePermission('members.invite');
        return $this->render('stokvel/members/invite', [
            'title' => 'Invite member',
            'activeNav' => 'members',
            'navContext' => 'app',
        ]);
    }

    public function invite(Request $request): Response
    {
        $this->requirePermission('members.invite');
        $this->requireStokvel();
        $data = [
            'email' => trim((string) $request->input('email', '')),
            'mobile' => trim((string) $request->input('mobile', '')),
            'role' => (string) $request->input('role', 'ordinary'),
        ];
        $roleMap = [
            'ordinary' => 'ordinary_member',
            'committee' => 'committee_member',
        ];
        $roleCode = $roleMap[$data['role']] ?? $data['role'];
        $validator = Validator::make($data, [
            'email' => 'required|email',
            'role' => 'required|in:chairperson,treasurer,secretary,committee,ordinary,auditor',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Could not send invitation.');
            return $this->redirect('/app/members/invite');
        }

        $role = $this->db()->fetch(
            'SELECT id FROM roles WHERE code = :code AND stokvel_id IS NULL LIMIT 1',
            ['code' => $roleCode]
        );

        /** @var \App\Services\Membership\MembershipService $memberships */
        $memberships = app()->container()->get(\App\Services\Membership\MembershipService::class);
        $memberships->invite(
            $data['email'],
            $data['mobile'] !== '' ? $data['mobile'] : null,
            $role ? (int) $role['id'] : null,
            (int) $this->userId()
        );

        Session::flash('success', 'Invitation sent to ' . $data['email'] . '.');
        return $this->redirect('/app/members');
    }

    public function suspend(Request $request, string $id): Response
    {
        $this->requirePermission('members.manage');
        $this->updateMemberStatus((int) $id, 'suspended');
        Session::flash('success', 'Member suspended.');
        return $this->redirectBack($request, '/app/members/' . $id);
    }

    public function reinstate(Request $request, string $id): Response
    {
        $this->requirePermission('members.manage');
        $this->updateMemberStatus((int) $id, 'active');
        Session::flash('success', 'Member reinstated.');
        return $this->redirectBack($request, '/app/members/' . $id);
    }

    public function resign(Request $request, string $id): Response
    {
        $this->requirePermission('members.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE memberships SET status = :st, resigned_at = :ra, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['st' => 'resigned', 'ra' => now_utc(), 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Member marked as resigned.');
        return $this->redirect('/app/members');
    }

    public function statement(Request $request, string $id): Response
    {
        $this->requirePermission('members.view');
        $sid = $this->requireStokvel();
        $member = $this->db()->fetch(
            'SELECT sm.*, p.first_name, p.last_name FROM memberships sm JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE sm.id = :id AND sm.stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$member) {
            return $this->redirect('/app/members');
        }

        $lines = $this->memberStatementLines($sid, (int) $member['id']);

        $layout = $request->query('print') ? 'layouts/print' : 'layouts/app';
        return $this->render('stokvel/members/statement', [
            'title' => 'Member statement',
            'activeNav' => 'members',
            'navContext' => 'app',
            'member' => $member,
            'lines' => $lines,
        ], $layout);
    }

    private function updateMemberStatus(int $memberId, string $status): void
    {
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE memberships SET status = :st, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['st' => $status, 'ua' => now_utc(), 'id' => $memberId, 'sid' => $sid]
        );
    }

    /** @return list<array<string, mixed>> */
    private function memberStatementLines(int $stokvelId, int $membershipId): array
    {
        $charges = $this->db()->fetchAll(
            "SELECT due_date AS entry_date, description, amount, 'charge' AS entry_type, charge_code AS reference
             FROM member_charges
             WHERE stokvel_id = :sid AND membership_id = :mid
             ORDER BY due_date DESC, id DESC
             LIMIT 100",
            ['sid' => $stokvelId, 'mid' => $membershipId]
        );
        $payments = $this->db()->fetchAll(
            "SELECT paid_at AS entry_date, CONCAT('Payment ', COALESCE(payment_method, '')) AS description,
                    amount, 'payment' AS entry_type, COALESCE(transaction_ref, CAST(id AS CHAR)) AS reference
             FROM payment_transactions
             WHERE stokvel_id = :sid AND membership_id = :mid
               AND status IN ('posted','completed','allocated','confirmed')
             ORDER BY paid_at DESC, id DESC
             LIMIT 100",
            ['sid' => $stokvelId, 'mid' => $membershipId]
        );

        $lines = array_merge($charges, $payments);
        usort($lines, static function (array $a, array $b): int {
            return strcmp((string) ($b['entry_date'] ?? ''), (string) ($a['entry_date'] ?? ''));
        });

        return array_slice($lines, 0, 200);
    }
}
