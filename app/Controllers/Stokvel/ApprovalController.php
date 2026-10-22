<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Loans\LoanService;

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
            "SELECT ar.*,
                    CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, '')) AS requested_by_name,
                    u.email AS requested_by_email
             FROM approval_requests ar
             LEFT JOIN users u ON u.id = ar.requested_by_user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE ar.stokvel_id = :sid AND ar.status = :status
             ORDER BY ar.created_at DESC",
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
            'SELECT * FROM approval_requests WHERE id = :id AND stokvel_id = :sid LIMIT 1',
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
        } else {
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

        $this->applySideEffects($row, $status, $notes, $userId);
    }

    /** @param array<string, mixed> $row */
    private function applySideEffects(array $row, string $status, string $notes, int $userId): void
    {
        $sid = (int) $row['stokvel_id'];
        $refType = (string) ($row['reference_type'] ?? $row['request_type'] ?? '');
        $refId = (int) ($row['reference_id'] ?? 0);
        if ($refId < 1) {
            return;
        }

        try {
            if (in_array($refType, ['loan_application', 'loan'], true)) {
                /** @var LoanService $loans */
                $loans = app()->container()->get(LoanService::class);
                if ($status === 'approved') {
                    $loans->committeeApprove(
                        $sid,
                        $refId,
                        $userId,
                        null,
                        $notes !== '' ? $notes : 'Approved via approvals dashboard'
                    );
                } else {
                    $loans->committeeReject(
                        $sid,
                        $refId,
                        $userId,
                        $notes !== '' ? $notes : 'Rejected via approvals dashboard'
                    );
                }
                return;
            }

            if ($refType === 'meeting_notes') {
                $this->db()->exec(
                    "UPDATE meetings SET status = :st, updated_at = :ua WHERE id = :id AND stokvel_id = :sid",
                    [
                        'st' => $status === 'approved' ? 'completed' : 'scheduled',
                        'ua' => now_utc(),
                        'id' => $refId,
                        'sid' => $sid,
                    ]
                );
            }

            if ($refType === 'burial_claim') {
                $this->db()->exec(
                    'UPDATE burial_claims
                     SET status = :st, decided_by_user_id = :by, decided_at = :da, updated_at = :ua
                     WHERE id = :id AND stokvel_id = :sid',
                    [
                        'st' => $status === 'approved' ? 'approved' : 'rejected',
                        'by' => $userId,
                        'da' => now_utc(),
                        'ua' => now_utc(),
                        'id' => $refId,
                        'sid' => $sid,
                    ]
                );
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Decision saved but follow-up action failed: ' . $e->getMessage());
        }
    }
}
