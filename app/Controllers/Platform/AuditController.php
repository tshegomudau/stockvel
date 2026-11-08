<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Services\PermissionService;
use App\Services\Platform\ApplicationLogReader;

/**
 * Platform-only audit log + application log viewer.
 */
final class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            throw new AuthorizationException('Platform access required to view audit logs.');
        }

        /** @var PermissionService $perms */
        $perms = app()->container()->get(PermissionService::class);
        $source = trim((string) $request->query('source', 'audit'));
        $canViewAppLogs = $perms->platformCan('application_logs.view')
            || $perms->platformCan('audit_logs.view');
        if ($source === 'application') {
            if (!$canViewAppLogs) {
                $perms->requirePlatformPermission('application_logs.view');
            }
            return $this->applicationLogs($request, true);
        }
        $perms->requirePlatformPermission('audit_logs.view');

        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $action = trim((string) $request->query('action', ''));
        $userId = $request->query('user_id');
        $stokvelId = $request->query('stokvel_id');
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $q = trim((string) $request->query('q', ''));
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
        if ($stokvelId !== null && $stokvelId !== '') {
            $where .= ' AND a.stokvel_id = :sid';
            $params['sid'] = (int) $stokvelId;
        }
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where .= ' AND a.created_at >= :df';
            $params['df'] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where .= ' AND a.created_at <= :dt';
            $params['dt'] = $dateTo . ' 23:59:59';
        }
        if ($q !== '') {
            $where .= ' AND (a.action LIKE :q OR a.entity_type LIKE :q OR u.email LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE {$where}",
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
            array_filter([
                'source' => 'audit',
                'action' => $action,
                'user_id' => $userId,
                'stokvel_id' => $stokvelId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'q' => $q,
            ], static fn ($v) => $v !== null && $v !== '')
        );

        return $this->render('platform/audit/index', [
            'title' => 'Audit log',
            'activeNav' => 'platform-audit',
            'navContext' => 'platform',
            'paginator' => $paginator,
            'filters' => [
                'source' => 'audit',
                'action' => $action,
                'user_id' => $userId,
                'stokvel_id' => $stokvelId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'q' => $q,
            ],
            'appLogItems' => null,
            'canViewAppLogs' => $canViewAppLogs,
        ]);
    }

    private function applicationLogs(Request $request, bool $canViewAppLogs = true): Response
    {
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $reader = new ApplicationLogReader(app()->basePath('storage/logs'));
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'level' => trim((string) $request->query('level', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];
        $result = $reader->search($filters, $page, $perPage);

        $queryBase = array_filter(array_merge($filters, ['source' => 'application']), static fn ($v) => $v !== null && $v !== '');
        $totalPages = max(1, (int) ceil($result['total'] / max(1, $result['per_page'])));

        return $this->render('platform/audit/index', [
            'title' => 'Application logs',
            'activeNav' => 'platform-audit',
            'navContext' => 'platform',
            'paginator' => null,
            'appLogItems' => $result['items'],
            'appLogTotal' => $result['total'],
            'appLogPage' => $result['page'],
            'appLogPerPage' => $result['per_page'],
            'appLogTotalPages' => $totalPages,
            'appLogQuery' => $queryBase,
            'filters' => array_merge($filters, ['source' => 'application']),
            'canViewAppLogs' => $canViewAppLogs,
        ]);
    }
}
