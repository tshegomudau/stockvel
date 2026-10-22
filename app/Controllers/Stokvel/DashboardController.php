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
        $tenant = $this->tenant();

        $canMembers = $tenant->can('members.view');
        $canContributions = $tenant->can('contributions.view');
        $canApprovals = $tenant->can('approvals.view');
        $canMeetings = $tenant->can('meetings.manage');
        $canLoans = $tenant->can('loans.manage');
        $canReports = $tenant->can('reports.view');
        $canInvite = $tenant->can('members.invite');
        $canCapture = $tenant->can('contributions.capture');

        $settings = $db->fetch(
            'SELECT loans_enabled, branding_primary_colour FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $sid]
        ) ?? [];
        $loansEnabled = !empty($settings['loans_enabled']);

        $stats = [];
        if ($canMembers) {
            $stats['members'] = (int) ($db->fetch(
                'SELECT COUNT(*) AS c FROM memberships WHERE stokvel_id = :sid AND status = :st',
                ['sid' => $sid, 'st' => 'active']
            )['c'] ?? 0);
        }
        if ($canContributions) {
            $stats['contributions_mtd'] = (float) ($db->fetch(
                "SELECT COALESCE(SUM(amount),0) AS t FROM payment_transactions
                 WHERE stokvel_id = :sid AND status IN ('posted','completed','allocated','confirmed')
                   AND MONTH(paid_at) = MONTH(UTC_TIMESTAMP())
                   AND YEAR(paid_at) = YEAR(UTC_TIMESTAMP())",
                ['sid' => $sid]
            )['t'] ?? 0);
            $stats['arrears'] = (float) ($db->fetch(
                "SELECT COALESCE(SUM(amount - amount_paid),0) AS t FROM member_charges
                 WHERE stokvel_id = :sid AND status IN ('pending','partial','overdue')",
                ['sid' => $sid]
            )['t'] ?? 0);
        }
        if ($canApprovals) {
            $stats['pending_approvals'] = (int) ($db->fetch(
                "SELECT COUNT(*) AS c FROM approval_requests WHERE stokvel_id = :sid AND status = 'pending'",
                ['sid' => $sid]
            )['c'] ?? 0);
        }
        if ($canMeetings) {
            $stats['upcoming_meetings'] = (int) ($db->fetch(
                "SELECT COUNT(*) AS c FROM meetings
                 WHERE stokvel_id = :sid AND scheduled_at >= UTC_TIMESTAMP() AND status IN ('scheduled','confirmed')",
                ['sid' => $sid]
            )['c'] ?? 0);
        }
        if ($canLoans && $loansEnabled) {
            $stats['active_loans'] = (int) ($db->fetch(
                "SELECT COUNT(*) AS c FROM loans WHERE stokvel_id = :sid AND status IN ('active','disbursed','arrears')",
                ['sid' => $sid]
            )['c'] ?? 0);
        }

        $chartLabels = [];
        $chartValues = [];
        if ($canContributions) {
            for ($i = 5; $i >= 0; $i--) {
                $month = (new \DateTimeImmutable('first day of this month', new \DateTimeZone('UTC')))->modify("-{$i} months");
                $row = $db->fetch(
                    "SELECT COALESCE(SUM(amount),0) AS t FROM payment_transactions
                     WHERE stokvel_id = :sid
                       AND MONTH(paid_at) = :m AND YEAR(paid_at) = :y
                       AND status IN ('posted','completed','allocated','confirmed')",
                    [
                        'sid' => $sid,
                        'm' => (int) $month->format('n'),
                        'y' => (int) $month->format('Y'),
                    ]
                );
                $chartLabels[] = $month->format('M');
                $chartValues[] = round((float) ($row['t'] ?? 0), 2);
            }
        }

        return $this->render('stokvel/dashboard', [
            'title' => 'Dashboard',
            'activeNav' => 'dashboard',
            'navContext' => 'app',
            'stats' => $stats,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'settings' => $settings,
            'can' => [
                'members' => $canMembers,
                'contributions' => $canContributions,
                'approvals' => $canApprovals,
                'meetings' => $canMeetings,
                'loans' => $canLoans && $loansEnabled,
                'reports' => $canReports,
                'invite' => $canInvite,
                'capture' => $canCapture,
            ],
        ]);
    }
}
