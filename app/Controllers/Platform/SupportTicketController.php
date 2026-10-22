<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class SupportTicketController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }
        $status = (string) $request->query('status', '');
        $params = [];
        $where = '1=1';
        if ($status !== '') {
            $where .= ' AND t.status = :status';
            $params['status'] = $status;
        }

        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM support_tickets t WHERE {$where}",
            "SELECT t.*, p.first_name, p.last_name, u.email
             FROM support_tickets t
             JOIN users u ON u.id = t.created_by_user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE {$where}
             ORDER BY t.created_at DESC",
            $params,
            $this->page($request),
            $this->perPage($request),
            url('/platform/support'),
            array_filter(['status' => $status])
        );

        return $this->render('platform/support/index', [
            'title' => 'Support tickets',
            'activeNav' => 'platform-support',
            'navContext' => 'platform',
            'paginator' => $paginator,
            'statusFilter' => $status,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }
        $ticket = $this->db()->fetch(
            'SELECT t.*, u.email, p.first_name, p.last_name
             FROM support_tickets t
             JOIN users u ON u.id = t.created_by_user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE t.id = :id LIMIT 1',
            ['id' => (int) $id]
        );
        if (!$ticket) {
            return $this->redirect('/platform/support');
        }
        $updates = $this->db()->fetchAll(
            'SELECT u.*, p.first_name, p.last_name FROM support_ticket_updates u
             LEFT JOIN user_profiles p ON p.user_id = u.user_id
             WHERE u.ticket_id = :id ORDER BY u.created_at ASC',
            ['id' => (int) $id]
        );

        return $this->render('platform/support/show', [
            'title' => 'Ticket #' . $id,
            'activeNav' => 'platform-support',
            'navContext' => 'platform',
            'ticket' => $ticket,
            'updates' => $updates,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin() && !$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }
        $status = (string) $request->input('status', 'open');
        $notes = trim((string) $request->input('body', ''));
        $allowed = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];
        if (!in_array($status, $allowed, true)) {
            Session::flash('error', 'Invalid status.');
            return $this->redirect('/platform/support/' . $id);
        }

        $now = now_utc();
        $this->db()->exec(
            'UPDATE support_tickets SET status = :st, platform_notes = COALESCE(:notes, platform_notes),
                assigned_to_user_id = :aid, updated_at = :ua,
                closed_at = CASE WHEN :st2 IN (\'resolved\',\'closed\') THEN :ua2 ELSE NULL END
             WHERE id = :id',
            [
                'st' => $status,
                'st2' => $status,
                'notes' => $notes !== '' ? $notes : null,
                'aid' => $this->userId(),
                'ua' => $now,
                'ua2' => $now,
                'id' => (int) $id,
            ]
        );
        if ($notes !== '') {
            $this->db()->insert(
                'INSERT INTO support_ticket_updates (ticket_id, user_id, status, body, is_internal, created_at)
                 VALUES (:tid, :uid, :st, :body, 0, :ca)',
                [
                    'tid' => (int) $id,
                    'uid' => $this->userId(),
                    'st' => $status,
                    'body' => $notes,
                    'ca' => $now,
                ]
            );
        }
        Session::flash('success', 'Ticket updated.');
        return $this->redirect('/platform/support/' . $id);
    }
}
