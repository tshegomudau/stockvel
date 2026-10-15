<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $db = $this->db();

        $stats = [
            'members' => (int) ($db->fetch(
                'SELECT COUNT(*) AS c FROM memberships WHERE stokvel_id = :sid AND status = :st',
                ['sid' => $sid, 'st' => 'active']
            )['c'] ?? 0),
            'contributions_mtd' => (float) ($db->fetch(
                "SELECT COALESCE(SUM(amount),0) AS t FROM payment_transactions
                 WHERE stokvel_id = :sid AND status IN ('posted','completed','allocated')
                   AND MONTH(paid_at) = MONTH(UTC_TIMESTAMP())
                   AND YEAR(paid_at) = YEAR(UTC_TIMESTAMP())",
                ['sid' => $sid]
            )['t'] ?? 0),
            'arrears' => (float) ($db->fetch(
                "SELECT COALESCE(SUM(amount - amount_paid),0) AS t FROM member_charges
                 WHERE stokvel_id = :sid AND status IN ('pending','partial','overdue')",
                ['sid' => $sid]
            )['t'] ?? 0),
            'pending_approvals' => (int) ($db->fetch(
                "SELECT COUNT(*) AS c FROM approval_requests WHERE stokvel_id = :sid AND status = 'pending'",
                ['sid' => $sid]
            )['c'] ?? 0),
            'upcoming_meetings' => (int) ($db->fetch(
                "SELECT COUNT(*) AS c FROM meetings
                 WHERE stokvel_id = :sid AND scheduled_at >= UTC_TIMESTAMP() AND status = 'scheduled'",
                ['sid' => $sid]
            )['c'] ?? 0),
            'active_loans' => (int) ($db->fetch(
                "SELECT COUNT(*) AS c FROM loans WHERE stokvel_id = :sid AND status IN ('active','disbursed','arrears')",
                ['sid' => $sid]
            )['c'] ?? 0),
        ];

        $recent = $db->fetchAll(
            'SELECT id, action, entity_type, created_at FROM audit_logs
             WHERE stokvel_id = :sid ORDER BY created_at DESC LIMIT 8',
            ['sid' => $sid]
        );

        return $this->render('stokvel/dashboard', [
            'title' => 'Dashboard',
            'activeNav' => 'dashboard',
            'navContext' => 'app',
            'stats' => $stats,
            'recentActivity' => $recent,
        ]);
    }
}
