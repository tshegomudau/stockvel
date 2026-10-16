<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('approvals.view');
        $sid = $this->requireStokvel();
        $status = (string) $request->query('status', 'pending');
        $params = ['sid' => $sid, 'status' => $status];

        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM approval_requests WHERE stokvel_id = :sid AND status = :status',
            'SELECT * FROM approval_requests WHERE stokvel_id = :sid AND status = :status ORDER BY created_at DESC',
            $params,
            $this->page($request),
            $this->perPage($request),
            url('/app/approvals'),
            ['status' => $status]
        );

        return $this->render('stokvel/approvals/index', [
            'title' => 'Approvals',
            'activeNav' => 'approvals',
            'navContext' => 'app',
            'paginator' => $paginator,
            'statusFilter' => $status,
        ]);
    }

    public function approve(Request $request, string $id): Response
    {
        $this->requirePermission('approvals.action');
        $this->decide((int) $id, 'approved', (string) $request->input('notes', ''));
        Session::flash('success', 'Approved.');
        return $this->redirectBack($request, '/app/approvals');
    }

    public function reject(Request $request, string $id): Response
    {
        $this->requirePermission('approvals.action');
        $this->decide((int) $id, 'rejected', (string) $request->input('notes', ''));
        Session::flash('success', 'Rejected.');
        return $this->redirectBack($request, '/app/approvals');
    }

    private function decide(int $id, string $status, string $notes): void
    {
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();
        $now = now_utc();

        $row = $this->db()->fetch(
            'SELECT id, status FROM approval_requests WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $id, 'sid' => $sid]
        );
        if ($row === null || ($row['status'] ?? '') !== 'pending') {
            Session::flash('error', 'Approval request not found or already decided.');
            return;
        }

        if ($status === 'approved') {
            $this->db()->exec(
                'UPDATE approval_requests
                 SET status = :st, approved_by_user_id = :by, rejected_by_user_id = NULL,
                     decision_notes = :notes, decided_at = :da, updated_at = :ua
                 WHERE id = :id AND stokvel_id = :sid AND status = :pending',
                [
                    'st' => 'approved',
                    'by' => $userId,
                    'notes' => $notes !== '' ? $notes : null,
                    'da' => $now,
                    'ua' => $now,
                    'id' => $id,
                    'sid' => $sid,
                    'pending' => 'pending',
                ]
            );
            return;
        }

        $this->db()->exec(
            'UPDATE approval_requests
             SET status = :st, rejected_by_user_id = :by, approved_by_user_id = NULL,
                 decision_notes = :notes, decided_at = :da, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid AND status = :pending',
            [
                'st' => 'rejected',
                'by' => $userId,
                'notes' => $notes !== '' ? $notes : null,
                'da' => $now,
                'ua' => $now,
                'id' => $id,
                'sid' => $sid,
                'pending' => 'pending',
            ]
        );
    }
}
