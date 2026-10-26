<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\FinanceException;
use App\Services\Approvals\ApprovalService;

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

        /** @var ApprovalService $approvals */
        $approvals = app()->container()->get(ApprovalService::class);
        $decisionMap = [];
        foreach ($paginator->items as $row) {
            $decisionMap[(int) $row['id']] = $approvals->decisionsFor($sid, (int) $row['id']);
        }

        return $this->render('stokvel/approvals/index', [
            'title' => 'My Approvals',
            'activeNav' => 'approvals',
            'navContext' => 'app',
            'paginator' => $paginator,
            'statusFilter' => $status,
            'decisionMap' => $decisionMap,
        ]);
    }

    public function approve(Request $request, string $id): Response
    {
        $this->requirePermission('approvals.action');
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();
        $notes = trim((string) $request->input('notes', ''));

        try {
            /** @var ApprovalService $approvals */
            $approvals = app()->container()->get(ApprovalService::class);
            $result = $approvals->approve($sid, (int) $id, $userId, $notes);
            if ($result['finalised']) {
                Session::flash('success', 'Request fully approved (' . $result['approval_count'] . '/' . $result['required_approvals'] . ').');
            } else {
                Session::flash(
                    'success',
                    'Your approval was recorded (' . $result['approval_count'] . '/' . $result['required_approvals'] . '). Still pending further approval.'
                );
            }
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirectBack($request, '/app/approvals');
    }

    public function reject(Request $request, string $id): Response
    {
        $this->requirePermission('approvals.action');
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();
        $reason = trim((string) $request->input('notes', $request->input('reason', '')));

        try {
            /** @var ApprovalService $approvals */
            $approvals = app()->container()->get(ApprovalService::class);
            $approvals->reject($sid, (int) $id, $userId, $reason);
            Session::flash('success', 'Request rejected.');
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirectBack($request, '/app/approvals');
    }
}
