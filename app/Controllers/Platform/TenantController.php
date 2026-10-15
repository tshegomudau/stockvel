<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }

        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $search = trim((string) $request->query('q', ''));
        $params = [];
        $where = '1=1';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR registration_number LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM stokvels WHERE {$where}",
            "SELECT id, name, status, created_at, registration_number FROM stokvels WHERE {$where} ORDER BY created_at DESC",
            $params,
            $page,
            $perPage,
            url('/platform/tenants'),
            $search !== '' ? ['q' => $search] : []
        );

        return $this->render('platform/tenants/index', [
            'title' => 'Stokvels',
            'activeNav' => 'platform-tenants',
            'navContext' => 'platform',
            'paginator' => $paginator,
            'search' => $search,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }

        $stokvel = $this->db()->fetch('SELECT * FROM stokvels WHERE id = :id LIMIT 1', ['id' => (int) $id]);
        if (!$stokvel) {
            return $this->redirect('/platform/tenants');
        }

        $members = $this->db()->fetchAll(
            'SELECT sm.*, p.first_name, p.last_name, u.email FROM memberships sm JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE sm.stokvel_id = :sid ORDER BY sm.created_at DESC LIMIT 50',
            ['sid' => (int) $id]
        );

        return $this->render('platform/tenants/show', [
            'title' => $stokvel['name'] ?? 'Stokvel',
            'activeNav' => 'platform-tenants',
            'navContext' => 'platform',
            'stokvel' => $stokvel,
            'members' => $members,
        ]);
    }

    public function suspend(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            return $this->redirect('/platform/tenants/' . $id);
        }
        $this->db()->exec('UPDATE stokvels SET status = :st, updated_at = :ua WHERE id = :id', [
            'st' => 'suspended',
            'ua' => now_utc(),
            'id' => (int) $id,
        ]);
        Session::flash('success', 'Stokvel suspended.');
        return $this->redirect('/platform/tenants/' . $id);
    }

    public function activate(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            return $this->redirect('/platform/tenants/' . $id);
        }
        $this->db()->exec('UPDATE stokvels SET status = :st, updated_at = :ua WHERE id = :id', [
            'st' => 'active',
            'ua' => now_utc(),
            'id' => (int) $id,
        ]);
        Session::flash('success', 'Stokvel activated.');
        return $this->redirect('/platform/tenants/' . $id);
    }
}
