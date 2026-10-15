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
        $sid = $this->requireStokvel();
        $data = [
            'email' => trim((string) $request->input('email', '')),
            'mobile' => trim((string) $request->input('mobile', '')),
            'role' => (string) $request->input('role', 'ordinary'),
        ];
        $validator = Validator::make($data, [
            'email' => 'required|email',
            'role' => 'required|in:chairperson,treasurer,secretary,committee,ordinary,auditor',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Could not send invitation.');
            return $this->redirect('/app/members/invite');
        }

        $token = bin2hex(random_bytes(32));
        $this->service(\App\Services\Membership\InvitationService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function create(array $data): void
            {
                $this->db->insert(
                    'INSERT INTO member_invitations (stokvel_id, email, mobile, role, token_hash, status, invited_by, created_at) VALUES (:sid, :email, :mobile, :role, :th, :st, :by, :ca)',
                    $data
                );
            }
        })->create([
            'sid' => $sid,
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?: null,
            'role' => $data['role'],
            'th' => hash('sha256', $token),
            'st' => 'pending',
            'by' => $this->userId(),
            'ca' => now_utc(),
        ]);

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

        $lines = $this->service(\App\Services\Finance\MemberStatementService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function lines(int $stokvelId, int $memberId): array
            {
                return $this->db->fetchAll(
                    'SELECT * FROM member_ledger_entries WHERE stokvel_id = :sid AND member_id = :mid ORDER BY entry_date DESC LIMIT 200',
                    ['sid' => $stokvelId, 'mid' => $memberId]
                );
            }
        })->lines($sid, (int) $member['id']);

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
}
