<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;

final class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }

        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $action = trim((string) $request->query('action', ''));
        $userId = $request->query('user_id');
        $params = [];
        $where = '1=1';
        if ($action !== '') {
            $where .= ' AND action = :action';
            $params['action'] = $action;
        }
        if ($userId !== null && $userId !== '') {
            $where .= ' AND user_id = :uid';
            $params['uid'] = (int) $userId;
        }

        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM audit_logs WHERE {$where}",
            "SELECT id, user_id, stokvel_id, action, entity_type, entity_id, ip_address, created_at FROM audit_logs WHERE {$where} ORDER BY created_at DESC",
            $params,
            $page,
            $perPage,
            url('/platform/audit'),
            array_filter(['action' => $action, 'user_id' => $userId])
        );

        return $this->render('platform/audit/index', [
            'title' => 'Audit log',
            'activeNav' => 'platform-audit',
            'navContext' => 'platform',
            'paginator' => $paginator,
            'filters' => ['action' => $action, 'user_id' => $userId],
        ]);
    }
}
