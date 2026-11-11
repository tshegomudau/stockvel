<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\MembershipRepository;
use App\Services\Analytics\GroupHealthService;
use App\Services\Finance\FundService;
use App\Services\Onboarding\OnboardingService;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $db = $this->db();
        $tenant = $this->tenant();

        $setupIncomplete = false;
        $canManageSetup = $tenant->can('settings.manage');
        if ($canManageSetup) {
            try {
                /** @var OnboardingService $onboarding */
                $onboarding = app()->container()->get(OnboardingService::class);
                $setupIncomplete = $onboarding->needsSetup($sid);
            } catch (\Throwable) {
                $setupIncomplete = false;
            }
        }

        // Soft nudge only — stay on dashboard so pending invites and metrics remain visible.
        // First login still lands on the wizard via home_path_for_user().

        $canMembers = $tenant->can('members.view');
        $canContributions = $tenant->can('contributions.view');
        $canApprovals = $tenant->can('approvals.view');
        $canMeetings = $tenant->can('meetings.manage');
        $canLoans = $tenant->can('loans.manage');
        $canReports = $tenant->can('reports.view');
        $canInvite = $tenant->can('members.invite');
        $canCapture = $tenant->can('contributions.capture');
        $canFees = $tenant->can('fees.manage');

        $settings = $db->fetch(
            'SELECT loans_enabled, branding_primary_colour FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $sid]
        ) ?? [];
        $loansEnabled = !empty($settings['loans_enabled']);

        $tz = new \DateTimeZone(app_timezone());
        $nowLocal = new \DateTimeImmutable('now', $tz);
        $monthStart = $nowLocal->modify('first day of this month')->setTime(0, 0, 0);
        $nextMonthStart = $monthStart->modify('+1 month');

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
                   AND paid_at >= :start AND paid_at < :end",
                [
                    'sid' => $sid,
                    'start' => $monthStart->format('Y-m-d H:i:s'),
                    'end' => $nextMonthStart->format('Y-m-d H:i:s'),
                ]
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
                 WHERE stokvel_id = :sid AND scheduled_at >= :now AND status IN ('scheduled','confirmed')",
                ['sid' => $sid, 'now' => now_utc()]
            )['c'] ?? 0);
        }
        if ($canLoans && $loansEnabled) {
            $stats['active_loans'] = (int) ($db->fetch(
                "SELECT COUNT(*) AS c FROM loans WHERE stokvel_id = :sid AND status IN ('active','disbursed','arrears')",
                ['sid' => $sid]
            )['c'] ?? 0);
            $stats['loans_outstanding'] = (float) ($db->fetch(
                "SELECT COALESCE(SUM(outstanding_balance),0) AS t FROM loans
                 WHERE stokvel_id = :sid AND status IN ('active','disbursed','arrears')",
                ['sid' => $sid]
            )['t'] ?? 0);
        }

        if ($canFees || $canReports) {
            try {
                /** @var FundService $funds */
                $funds = app()->container()->get(FundService::class);
                $funds->ensureDefaults($sid);
                $admin = $funds->findAdminFund($sid);
                if ($admin) {
                    $stats['admin_pocket_balance'] = (float) $funds->balance($sid, (int) $admin['id']);
                }
            } catch (\Throwable) {
                // Funds table may not exist on older DBs mid-migrate
            }
        }

        $healthScore = null;
        if ($canReports) {
            try {
                /** @var GroupHealthService $health */
                $health = app()->container()->get(GroupHealthService::class);
                $healthScore = $health->score($sid);
                $stats['group_health'] = $healthScore['score'];
            } catch (\Throwable) {
                $healthScore = null;
            }
        }

        $pendingInvitations = [];
        if ($canMembers || $canInvite) {
            try {
                /** @var MembershipRepository $memberships */
                $memberships = app()->container()->get(MembershipRepository::class);
                $pendingInvitations = $memberships->listPendingInvitations(50);
                $stats['pending_invitations'] = count($pendingInvitations);
            } catch (\Throwable) {
                $pendingInvitations = [];
            }
        }

        $chartLabels = [];
        $chartValues = [];
        if ($canContributions) {
            for ($i = 5; $i >= 0; $i--) {
                $month = (new \DateTimeImmutable('first day of this month', $tz))->modify("-{$i} months");
                $start = $month->setTime(0, 0, 0);
                $end = $start->modify('+1 month');
                $row = $db->fetch(
                    "SELECT COALESCE(SUM(amount),0) AS t FROM payment_transactions
                     WHERE stokvel_id = :sid
                       AND paid_at >= :start AND paid_at < :end
                       AND status IN ('posted','completed','allocated','confirmed')",
                    [
                        'sid' => $sid,
                        'start' => $start->format('Y-m-d H:i:s'),
                        'end' => $end->format('Y-m-d H:i:s'),
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
            'healthScore' => $healthScore,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'settings' => $settings,
            'setupIncomplete' => $setupIncomplete,
            'pendingInvitations' => $pendingInvitations,
            'can' => [
                'members' => $canMembers,
                'contributions' => $canContributions,
                'approvals' => $canApprovals,
                'meetings' => $canMeetings,
                'loans' => $canLoans && $loansEnabled,
                'reports' => $canReports,
                'invite' => $canInvite,
                'capture' => $canCapture,
                'fees' => $canFees,
                'onboarding' => $canManageSetup && $setupIncomplete,
            ],
        ]);
    }
}
