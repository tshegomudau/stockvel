<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();

        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM notifications WHERE stokvel_id = :sid AND user_id = :uid',
            'SELECT * FROM notifications WHERE stokvel_id = :sid AND user_id = :uid ORDER BY created_at DESC',
            ['sid' => $sid, 'uid' => $userId],
            $this->page($request),
            $this->perPage($request),
            url('/member/notifications')
        );

        return $this->render('member/notifications', [
            'title' => 'Notifications',
            'activeNav' => 'member-notifications',
            'navContext' => 'member',
            'paginator' => $paginator,
        ]);
    }

    public function markRead(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE notifications SET read_at = :ra WHERE id = :id AND stokvel_id = :sid AND user_id = :uid',
            ['ra' => now_utc(), 'id' => (int) $id, 'sid' => $sid, 'uid' => $this->userId()]
        );
        return $this->redirectBack($request, '/member/notifications');
    }
}
