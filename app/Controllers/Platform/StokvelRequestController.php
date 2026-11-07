<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Services\PermissionService;
use App\Services\Stokvel\StokvelCreationRequestService;

final class StokvelRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }
        app()->container()->get(PermissionService::class)->requirePlatformPermission('stokvel_requests.view');

        $status = trim((string) $request->query('status', 'pending'));
        $allowed = ['pending', 'approved', 'rejected', 'expired', 'claimed', ''];
        if (!in_array($status, $allowed, true)) {
            $status = 'pending';
        }
        $params = [];
        $where = '1=1';
        if ($status !== '') {
            $where .= ' AND status = :status';
            $params['status'] = $status;
        }
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM stokvel_creation_requests WHERE {$where}",
            "SELECT * FROM stokvel_creation_requests WHERE {$where} ORDER BY created_at DESC",
            $params,
            $page,
            $perPage,
            url('/platform/stokvel-requests'),
            array_filter(['status' => $status])
        );

        return $this->render('platform/stokvel-requests/index', [
            'title' => 'Stokvel creation requests',
            'activeNav' => 'platform-stokvel-requests',
            'navContext' => 'platform',
            'paginator' => $paginator,
            'filterStatus' => $status,
            'canApprove' => app()->container()->get(PermissionService::class)->platformCan('stokvel_requests.approve'),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }
        app()->container()->get(PermissionService::class)->requirePlatformPermission('stokvel_requests.view');
        $row = $this->db()->fetch(
            'SELECT * FROM stokvel_creation_requests WHERE id = :id LIMIT 1',
            ['id' => (int) $id]
        );
        if (!$row) {
            return $this->redirect('/platform/stokvel-requests');
        }

        return $this->render('platform/stokvel-requests/show', [
            'title' => 'Review request',
            'activeNav' => 'platform-stokvel-requests',
            'navContext' => 'platform',
            'requestRow' => $row,
            'canApprove' => app()->container()->get(PermissionService::class)->platformCan('stokvel_requests.approve'),
        ]);
    }

    public function approve(Request $request, string $id): Response
    {
        $this->requireAuth();
        app()->container()->get(PermissionService::class)->requirePlatformPermission('stokvel_requests.approve');
        try {
            app()->container()->get(StokvelCreationRequestService::class)
                ->approve((int) $id, (int) $this->userId());
            Session::flash('success', 'Request approved. Claim email sent to the requester.');
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect('/platform/stokvel-requests/' . $id);
    }

    public function reject(Request $request, string $id): Response
    {
        $this->requireAuth();
        app()->container()->get(PermissionService::class)->requirePlatformPermission('stokvel_requests.approve');
        try {
            app()->container()->get(StokvelCreationRequestService::class)
                ->reject((int) $id, (int) $this->userId(), trim((string) $request->input('reason', '')));
            Session::flash('success', 'Request rejected.');
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect('/platform/stokvel-requests/' . $id);
    }
}
