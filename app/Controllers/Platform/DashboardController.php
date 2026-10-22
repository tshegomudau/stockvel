<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }

        $db = $this->db();
        $failedJobs = (int) ($db->fetch(
            "SELECT COUNT(*) AS c FROM notification_jobs WHERE status IN ('failed', 'dead')"
        )['c'] ?? 0);
        $openTickets = (int) ($db->fetch(
            "SELECT COUNT(*) AS c FROM support_tickets
             WHERE status NOT IN ('closed', 'resolved', 'cancelled')"
        )['c'] ?? 0);

        $canJobs = $this->tenant()->isPlatformAdmin();
        $canSupport = $this->tenant()->isPlatformAdmin();

        $metrics = [
            'stokvels' => (int) ($db->fetch('SELECT COUNT(*) AS c FROM stokvels')['c'] ?? 0),
            'users' => (int) ($db->fetch('SELECT COUNT(*) AS c FROM users')['c'] ?? 0),
            'active_stokvels' => (int) ($db->fetch("SELECT COUNT(*) AS c FROM stokvels WHERE status = 'active'")['c'] ?? 0),
            'suspended_stokvels' => (int) ($db->fetch("SELECT COUNT(*) AS c FROM stokvels WHERE status = 'suspended'")['c'] ?? 0),
            'failed_jobs' => $failedJobs,
            'open_ticket_count' => $openTickets,
            'security_events' => (int) ($db->fetch(
                "SELECT COUNT(*) AS c FROM audit_logs WHERE action LIKE 'auth.%' OR action LIKE 'security.%'"
            )['c'] ?? 0),
        ];

        $alerts = [];
        if ($canJobs && $failedJobs > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => $failedJobs === 1
                    ? 'There is 1 failed notification job.'
                    : "There are {$failedJobs} failed notification jobs.",
                'url' => url('/platform/jobs'),
                'link_label' => 'View failed jobs',
            ];
        }
        if ($canSupport && $openTickets > 0) {
            $alerts[] = [
                'type' => 'info',
                'message' => $openTickets === 1
                    ? 'There is 1 open support ticket.'
                    : "There are {$openTickets} open support tickets.",
                'url' => url('/platform/support'),
                'link_label' => 'View support tickets',
            ];
        }

        return $this->render('platform/dashboard', [
            'title' => 'Platform dashboard',
            'activeNav' => 'platform-dashboard',
            'metrics' => $metrics,
            'alerts' => $alerts,
            'navContext' => 'platform',
        ]);
    }
}
