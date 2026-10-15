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
            'SELECT COUNT(*) AS c FROM failed_jobs WHERE resolved_at IS NULL',
            'SELECT id, queue, job_class, exception_message, failed_at, attempts FROM failed_jobs WHERE resolved_at IS NULL ORDER BY failed_at DESC',
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
