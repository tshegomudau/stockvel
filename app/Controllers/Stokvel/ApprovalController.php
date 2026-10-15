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
            'SELECT COUNT(*) AS c FROM approvals WHERE stokvel_id = :sid AND status = :status',
            'SELECT * FROM approvals WHERE stokvel_id = :sid AND status = :status ORDER BY created_at DESC',
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
        $this->db()->exec(
            'UPDATE approvals SET status = :st, decided_by = :by, decision_notes = :notes, decided_at = :da, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            [
                'st' => $status,
                'by' => $this->userId(),
                'notes' => $notes ?: null,
                'da' => now_utc(),
                'ua' => now_utc(),
                'id' => $id,
                'sid' => $sid,
            ]
        );
    }
}
