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
            $where .= ' AND a.action = :action';
            $params['action'] = $action;
        }
        if ($userId !== null && $userId !== '') {
            $where .= ' AND a.user_id = :uid';
            $params['uid'] = (int) $userId;
        }

        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM audit_logs a WHERE {$where}",
            "SELECT a.id, a.user_id, a.stokvel_id, a.action, a.entity_type, a.entity_id, a.ip_address, a.created_at,
                    u.email, p.first_name, p.last_name, s.name AS stokvel_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             LEFT JOIN stokvels s ON s.id = a.stokvel_id
             WHERE {$where}
             ORDER BY a.created_at DESC",
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
