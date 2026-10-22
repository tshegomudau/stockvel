<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->stokvelId();
        $uid = (int) $this->userId();
        $params = ['uid' => $uid];
        $where = 'created_by_user_id = :uid';
        if ($sid) {
            $where .= ' OR stokvel_id = :sid';
            $params['sid'] = $sid;
        }

        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM support_tickets WHERE {$where}",
            "SELECT * FROM support_tickets WHERE {$where} ORDER BY created_at DESC",
            $params,
            $this->page($request),
            $this->perPage($request),
            url('/app/support')
        );

        return $this->render('stokvel/support/index', [
            'title' => 'Support & bugs',
            'activeNav' => 'support',
            'navContext' => 'app',
            'paginator' => $paginator,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requireAuth();
        return $this->render('stokvel/support/create', [
            'title' => 'Log a bug / support request',
            'activeNav' => 'support',
            'navContext' => 'app',
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
        ], [
            'subject.required' => 'Please enter a short subject for your ticket.',
            'subject.min' => 'Subject must be at least 3 characters.',
            'body.required' => 'Please describe the issue in the details field.',
            'body.min' => 'Please add a bit more detail (at least 5 characters).',
            'category.in' => 'Please choose a valid category.',
            'priority.in' => 'Please choose a valid priority.',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            $this->flashErrors($validator->errors());
            Session::flash('error', $validator->firstError() ?? 'Please complete the ticket details.');
            return $this->redirect('/app/support/create');
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

        Session::flash('success', 'Ticket #' . $id . ' logged. Our team will update you here.');
        return $this->redirect('/app/support/' . $id);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();
        $ticket = $this->db()->fetch(
            'SELECT * FROM support_tickets WHERE id = :id LIMIT 1',
            ['id' => (int) $id]
        );
        if (!$ticket || ((int) $ticket['created_by_user_id'] !== (int) $this->userId()
            && (int) ($ticket['stokvel_id'] ?? 0) !== (int) ($this->stokvelId() ?? 0)
            && !$this->tenant()->isPlatformAdmin())) {
            return $this->redirect('/app/support');
        }

        $updates = $this->db()->fetchAll(
            'SELECT u.*, p.first_name, p.last_name FROM support_ticket_updates u
             LEFT JOIN user_profiles p ON p.user_id = u.user_id
             WHERE u.ticket_id = :id AND u.is_internal = 0
             ORDER BY u.created_at ASC',
            ['id' => (int) $id]
        );

        return $this->render('stokvel/support/show', [
            'title' => 'Ticket #' . $id,
            'activeNav' => 'support',
            'navContext' => 'app',
            'ticket' => $ticket,
            'updates' => $updates,
        ]);
    }

    public function reply(Request $request, string $id): Response
    {
        $this->requireAuth();
        $ticket = $this->db()->fetch('SELECT * FROM support_tickets WHERE id = :id LIMIT 1', ['id' => (int) $id]);
        if (!$ticket || (int) $ticket['created_by_user_id'] !== (int) $this->userId()) {
            return $this->redirect('/app/support');
        }
        $body = trim((string) $request->input('body', ''));
        if (strlen($body) < 2) {
            Session::flash('error', 'Reply cannot be empty.');
            return $this->redirect('/app/support/' . $id);
        }
        $this->db()->insert(
            'INSERT INTO support_ticket_updates (ticket_id, user_id, body, is_internal, created_at)
             VALUES (:tid, :uid, :body, 0, :ca)',
            ['tid' => (int) $id, 'uid' => $this->userId(), 'body' => $body, 'ca' => now_utc()]
        );
        $this->db()->exec(
            'UPDATE support_tickets SET updated_at = :ua, status = CASE WHEN status = \'resolved\' THEN \'open\' ELSE status END WHERE id = :id',
            ['ua' => now_utc(), 'id' => (int) $id]
        );
        Session::flash('success', 'Reply added.');
        return $this->redirect('/app/support/' . $id);
    }
}
