<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Analytics\GroupHealthService;

final class HealthController extends Controller
{
    public function show(Request $request): Response
    {
        $this->requirePermission('health.view');
        $sid = $this->requireStokvel();

        /** @var GroupHealthService $health */
        $health = app()->container()->get(GroupHealthService::class);
        $report = $health->score($sid);

        return $this->render('stokvel/health/show', [
            'title' => 'Group health',
            'activeNav' => 'health',
            'navContext' => 'app',
            'report' => $report,
        ]);
    }
}
