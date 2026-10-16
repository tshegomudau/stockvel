<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;

final class JobController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }

        $page = $this->page($request);
        $perPage = $this->perPage($request);

        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM notification_jobs WHERE status IN ('failed', 'dead')",
            "SELECT id, channel, recipient, status, last_error, attempt_count, max_attempts, updated_at
             FROM notification_jobs
             WHERE status IN ('failed', 'dead')
             ORDER BY updated_at DESC",
            [],
            $page,
            $perPage,
            url('/platform/jobs')
        );

        return $this->render('platform/jobs/index', [
            'title' => 'Failed jobs',
            'activeNav' => 'platform-jobs',
            'navContext' => 'platform',
            'paginator' => $paginator,
        ]);
    }
}
