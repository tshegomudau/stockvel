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
        $failedJobs = 0;
        try {
            $failedJobs = (int) ($db->fetch(
                "SELECT COUNT(*) AS c FROM notification_jobs WHERE status = 'failed'"
            )['c'] ?? 0);
        } catch (\Throwable) {
            $failedJobs = 0;
        }
        $metrics = [
            'stokvels' => (int) ($db->fetch('SELECT COUNT(*) AS c FROM stokvels')['c'] ?? 0),
            'users' => (int) ($db->fetch('SELECT COUNT(*) AS c FROM users')['c'] ?? 0),
            'active_stokvels' => (int) ($db->fetch("SELECT COUNT(*) AS c FROM stokvels WHERE status = 'active'")['c'] ?? 0),
            'suspended_stokvels' => (int) ($db->fetch("SELECT COUNT(*) AS c FROM stokvels WHERE status = 'suspended'")['c'] ?? 0),
            'failed_jobs' => $failedJobs,
            'security_events' => (int) ($db->fetch(
                "SELECT COUNT(*) AS c FROM audit_logs WHERE action LIKE 'auth.%' OR action LIKE 'security.%'"
            )['c'] ?? 0),
        ];

        return $this->render('platform/dashboard', [
            'title' => 'Platform dashboard',
            'activeNav' => 'platform-dashboard',
            'metrics' => $metrics,
            'navContext' => 'platform',
        ]);
    }
}
