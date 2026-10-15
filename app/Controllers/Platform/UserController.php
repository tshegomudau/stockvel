<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;

final class UserController extends Controller
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
            $where .= ' AND (email LIKE :q OR first_name LIKE :q OR last_name LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM users WHERE {$where}",
            "SELECT u.id, p.first_name, p.last_name, u.email, u.platform_role, u.email_verified_at, u.created_at FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id WHERE {$where} ORDER BY created_at DESC",
            $params,
            $page,
            $perPage,
            url('/platform/users'),
            $search !== '' ? ['q' => $search] : []
        );

        return $this->render('platform/users/index', [
            'title' => 'Users',
            'activeNav' => 'platform-users',
            'navContext' => 'platform',
            'paginator' => $paginator,
            'search' => $search,
        ]);
    }
}
