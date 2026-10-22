<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Platform may update stokvel chairpersons and grant them full permission sets.
 * Ordinary tenant users are managed inside each stokvel, not here.
 */
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
        $where = "r.code = 'chairperson'";
        if ($search !== '') {
            $where .= ' AND (u.email LIKE :q OR p.first_name LIKE :q OR p.last_name LIKE :q OR s.name LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(DISTINCT u.id) AS c
             FROM users u
             INNER JOIN memberships sm ON sm.user_id = u.id
             INNER JOIN membership_roles mr ON mr.membership_id = sm.id
             INNER JOIN roles r ON r.id = mr.role_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             LEFT JOIN stokvels s ON s.id = sm.stokvel_id
             WHERE {$where}",
            "SELECT DISTINCT u.id, p.first_name, p.last_name, u.email, u.platform_role, u.email_verified_at, u.created_at,
                    s.name AS stokvel_name, s.id AS stokvel_id, sm.id AS membership_id
             FROM users u
             INNER JOIN memberships sm ON sm.user_id = u.id
             INNER JOIN membership_roles mr ON mr.membership_id = sm.id
             INNER JOIN roles r ON r.id = mr.role_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             LEFT JOIN stokvels s ON s.id = sm.stokvel_id
             WHERE {$where}
             ORDER BY u.created_at DESC",
            $params,
            $page,
            $perPage,
            url('/platform/users'),
            $search !== '' ? ['q' => $search] : []
        );

        $withoutChair = $this->db()->fetchAll(
            "SELECT s.id, s.name, s.status,
                    (SELECT COUNT(*) FROM memberships m WHERE m.stokvel_id = s.id AND m.status IN ('active','suspended')) AS member_count
             FROM stokvels s
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM memberships sm
                 INNER JOIN membership_roles mr ON mr.membership_id = sm.id
                 INNER JOIN roles r ON r.id = mr.role_id AND r.code = 'chairperson'
                 WHERE sm.stokvel_id = s.id
             )
             ORDER BY s.name ASC
             LIMIT 100"
        );

        return $this->render('platform/users/index', [
            'title' => 'Stokvel chairpersons',
            'activeNav' => 'platform-users',
            'navContext' => 'platform',
            'paginator' => $paginator,
            'search' => $search,
            'withoutChair' => $withoutChair,
        ]);
    }

    public function appointForm(Request $request, string $stokvelId): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            Session::flash('error', 'Only platform super admins can appoint chairpersons.');
            return $this->redirect('/platform/users');
        }

        $sid = (int) $stokvelId;
        $stokvel = $this->db()->fetch('SELECT id, name, status FROM stokvels WHERE id = :id LIMIT 1', ['id' => $sid]);
        if (!$stokvel) {
            Session::flash('error', 'Stokvel not found.');
            return $this->redirect('/platform/users');
        }

        $existing = $this->db()->fetch(
            "SELECT sm.id
             FROM memberships sm
             INNER JOIN membership_roles mr ON mr.membership_id = sm.id
             INNER JOIN roles r ON r.id = mr.role_id AND r.code = 'chairperson'
             WHERE sm.stokvel_id = :sid
             LIMIT 1",
            ['sid' => $sid]
        );
        if ($existing) {
            Session::flash('error', 'This stokvel already has a chairperson.');
            return $this->redirect('/platform/users');
        }

        $q = trim((string) $request->query('q', ''));
        $params = ['sid' => $sid];
        $where = 'sm.stokvel_id = :sid AND sm.status IN (\'active\', \'suspended\')';
        if ($q !== '') {
            $where .= ' AND (u.email LIKE :q OR p.first_name LIKE :q OR p.last_name LIKE :q OR sm.member_number LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $members = $this->db()->fetchAll(
            "SELECT sm.id AS membership_id, sm.member_number, sm.status, sm.user_id,
                    u.email, p.first_name, p.last_name
             FROM memberships sm
             INNER JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE {$where}
             ORDER BY p.last_name, p.first_name, u.email
             LIMIT 500",
            $params
        );

        return $this->render('platform/users/appoint', [
            'title' => 'Appoint chairperson',
            'activeNav' => 'platform-users',
            'navContext' => 'platform',
            'stokvel' => $stokvel,
            'members' => $members,
            'search' => $q,
        ]);
    }

    /** Appoint from tenant show (POST body: membership_id). */
    public function appointChairperson(Request $request, string $id): Response
    {
        return $this->appoint($request, $id);
    }

    public function appoint(Request $request, string $stokvelId): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            Session::flash('error', 'Only platform super admins can appoint chairpersons.');
            return $this->redirect('/platform/users');
        }

        $sid = (int) $stokvelId;
        $membershipId = (int) $request->input('membership_id', 0);
        $stokvel = $this->db()->fetch('SELECT id, name FROM stokvels WHERE id = :id LIMIT 1', ['id' => $sid]);
        if (!$stokvel) {
            Session::flash('error', 'Stokvel not found.');
            return $this->redirect('/platform/users');
        }

        $existing = $this->db()->fetch(
            "SELECT sm.id
             FROM memberships sm
             INNER JOIN membership_roles mr ON mr.membership_id = sm.id
             INNER JOIN roles r ON r.id = mr.role_id AND r.code = 'chairperson'
             WHERE sm.stokvel_id = :sid
             LIMIT 1",
            ['sid' => $sid]
        );
        if ($existing) {
            Session::flash('error', 'This stokvel already has a chairperson.');
            return $this->redirect('/platform/users');
        }

        $member = $this->db()->fetch(
            "SELECT sm.id, sm.user_id, sm.status
             FROM memberships sm
             WHERE sm.id = :mid AND sm.stokvel_id = :sid
             LIMIT 1",
            ['mid' => $membershipId, 'sid' => $sid]
        );
        if (!$member || !in_array((string) ($member['status'] ?? ''), ['active', 'suspended'], true)) {
            Session::flash('error', 'Select an active member of this stokvel.');
            return $this->redirect('/platform/users/appoint/' . $sid);
        }

        $role = $this->db()->fetch(
            "SELECT id FROM roles WHERE code = 'chairperson' AND stokvel_id IS NULL LIMIT 1"
        );
        if (!$role) {
            Session::flash('error', 'Chairperson role is missing from the system.');
            return $this->redirect('/platform/users');
        }

        $now = now_utc();
        $this->db()->insert(
            'INSERT INTO membership_roles
                (stokvel_id, membership_id, role_id, assigned_by_user_id, assigned_at, created_at)
             VALUES (:sid, :mid, :rid, :by, :aa, :ca)',
            [
                'sid' => $sid,
                'mid' => $membershipId,
                'rid' => (int) $role['id'],
                'by' => $this->userId(),
                'aa' => $now,
                'ca' => $now,
            ]
        );

        Session::flash('success', 'Chairperson appointed for ' . (string) ($stokvel['name'] ?? 'stokvel') . '.');
        return $this->redirect('/platform/users');
    }

    public function grantAll(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            Session::flash('error', 'Only platform super admins can grant full permissions.');
            return $this->redirect('/platform/users');
        }

        $membershipId = (int) $request->input('membership_id', 0);
        $chair = $this->db()->fetch(
            "SELECT sm.id AS membership_id, sm.stokvel_id, sm.user_id
             FROM memberships sm
             INNER JOIN membership_roles mr ON mr.membership_id = sm.id
             INNER JOIN roles r ON r.id = mr.role_id AND r.code = 'chairperson'
             WHERE sm.user_id = :uid AND sm.id = :mid
             LIMIT 1",
            ['uid' => (int) $id, 'mid' => $membershipId]
        );
        if (!$chair) {
            Session::flash('error', 'Chairperson membership not found.');
            return $this->redirect('/platform/users');
        }

        $permissions = $this->db()->fetchAll('SELECT id FROM permissions');
        $now = now_utc();
        $granted = 0;
        foreach ($permissions as $perm) {
            $exists = $this->db()->fetch(
                'SELECT id FROM membership_permissions
                 WHERE membership_id = :mid AND permission_id = :pid LIMIT 1',
                ['mid' => (int) $chair['membership_id'], 'pid' => (int) $perm['id']]
            );
            if ($exists) {
                continue;
            }
            $this->db()->insert(
                'INSERT INTO membership_permissions
                    (stokvel_id, membership_id, permission_id, granted, granted_by_user_id, created_at, updated_at)
                 VALUES (:sid, :mid, :pid, 1, :by, :ca, :ua)',
                [
                    'sid' => (int) $chair['stokvel_id'],
                    'mid' => (int) $chair['membership_id'],
                    'pid' => (int) $perm['id'],
                    'by' => $this->userId(),
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
            ++$granted;
        }

        $role = $this->db()->fetch(
            "SELECT id FROM roles WHERE code = 'chairperson' AND stokvel_id IS NULL LIMIT 1"
        );
        if ($role) {
            foreach ($permissions as $perm) {
                $link = $this->db()->fetch(
                    'SELECT role_id FROM role_permissions WHERE role_id = :rid AND permission_id = :pid LIMIT 1',
                    ['rid' => (int) $role['id'], 'pid' => (int) $perm['id']]
                );
                if (!$link) {
                    $this->db()->insert(
                        'INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:rid, :pid, :ca)',
                        ['rid' => (int) $role['id'], 'pid' => (int) $perm['id'], 'ca' => $now]
                    );
                }
            }
        }

        Session::flash('success', "Granted {$granted} additional permission(s) to chairperson.");
        return $this->redirect('/platform/users');
    }
}
