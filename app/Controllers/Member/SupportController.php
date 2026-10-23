<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

/**
 * Member portal support / bug logging — always available (no special permission).
 */
final class SupportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $uid = (int) $this->userId();
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM support_tickets WHERE created_by_user_id = :uid',
            'SELECT * FROM support_tickets WHERE created_by_user_id = :uid ORDER BY created_at DESC',
            ['uid' => $uid],
            $this->page($request),
            $this->perPage($request),
            url('/member/support')
        );

        return $this->render('member/support/index', [
            'title' => 'Support & bugs',
            'activeNav' => 'member-support',
            'navContext' => 'member',
            'paginator' => $paginator,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requireAuth();
        return $this->render('member/support/create', [
            'title' => 'Log a bug / support request',
            'activeNav' => 'member-support',
            'navContext' => 'member',
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requireAuth();
        $data = [
            'subject' => trim((string) $request->input('subject', '')),
            'category' => (string) $request->input('category', 'bug'),
            'priority' => (string) $request->input('priority', 'normal'),
            'body' => trim((string) $request->input('body', '')),
        ];
        $validator = Validator::make($data, [
            'subject' => 'required|min:3',
            'body' => 'required|min:5',
            'category' => 'required|in:bug,support,feature,billing',
            'priority' => 'required|in:low,normal,high,urgent',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            $this->flashErrors($validator->errors());
            Session::flash('error', $validator->firstError() ?? 'Please complete the ticket details.');
            return $this->redirect('/member/support/create');
        }

        $now = now_utc();
        $id = (int) $this->db()->insert(
            'INSERT INTO support_tickets
                (stokvel_id, created_by_user_id, subject, category, priority, status, body, created_at, updated_at)
             VALUES
                (:sid, :uid, :subject, :category, :priority, :status, :body, :ca, :ua)',
            [
                'sid' => $this->stokvelId(),
                'uid' => $this->userId(),
                'subject' => $data['subject'],
                'category' => $data['category'],
                'priority' => $data['priority'],
                'status' => 'open',
                'body' => $data['body'],
                'ca' => $now,
                'ua' => $now,
            ]
        );

        Session::flash('success', 'Ticket #' . $id . ' logged.');
        return $this->redirect('/member/support/' . $id);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();
        $ticket = $this->db()->fetch(
            'SELECT * FROM support_tickets WHERE id = :id AND created_by_user_id = :uid LIMIT 1',
            ['id' => (int) $id, 'uid' => (int) $this->userId()]
        );
        if (!$ticket) {
            return $this->redirect('/member/support');
        }

        $updates = $this->db()->fetchAll(
            'SELECT u.*, p.first_name, p.last_name FROM support_ticket_updates u
             LEFT JOIN user_profiles p ON p.user_id = u.user_id
             WHERE u.ticket_id = :id AND u.is_internal = 0
             ORDER BY u.created_at ASC',
            ['id' => (int) $id]
        );

        return $this->render('member/support/show', [
            'title' => 'Ticket #' . $id,
            'activeNav' => 'member-support',
            'navContext' => 'member',
            'ticket' => $ticket,
            'updates' => $updates,
        ]);
    }

    public function reply(Request $request, string $id): Response
    {
        $this->requireAuth();
        $ticket = $this->db()->fetch(
            'SELECT * FROM support_tickets WHERE id = :id AND created_by_user_id = :uid LIMIT 1',
            ['id' => (int) $id, 'uid' => (int) $this->userId()]
        );
        if (!$ticket) {
            return $this->redirect('/member/support');
        }
        $body = trim((string) $request->input('body', ''));
        if (strlen($body) < 2) {
            Session::flash('error', 'Reply cannot be empty.');
            return $this->redirect('/member/support/' . $id);
        }
        $this->db()->insert(
            'INSERT INTO support_ticket_updates (ticket_id, user_id, body, is_internal, created_at)
             VALUES (:tid, :uid, :body, 0, :ca)',
            ['tid' => (int) $id, 'uid' => $this->userId(), 'body' => $body, 'ca' => now_utc()]
        );
        $this->db()->exec(
            'UPDATE support_tickets SET updated_at = :ua WHERE id = :id',
            ['ua' => now_utc(), 'id' => (int) $id]
        );
        Session::flash('success', 'Reply added.');
        return $this->redirect('/member/support/' . $id);
    }
}
