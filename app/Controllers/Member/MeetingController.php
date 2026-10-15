<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class MeetingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $meetings = $this->db()->fetchAll(
            'SELECT m.*, ma.acknowledged_at FROM meetings m LEFT JOIN meeting_acknowledgements ma ON ma.meeting_id = m.id AND ma.user_id = :uid WHERE m.stokvel_id = :sid ORDER BY m.scheduled_at DESC LIMIT 50',
            ['sid' => $sid, 'uid' => $this->userId()]
        );

        return $this->render('member/meetings', [
            'title' => 'Meetings',
            'activeNav' => 'member-meetings',
            'navContext' => 'member',
            'meetings' => $meetings,
        ]);
    }

    public function acknowledge(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $meeting = $this->db()->fetch(
            'SELECT id FROM meetings WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$meeting) {
            return $this->redirect('/member/meetings');
        }

        $this->db()->exec(
            'INSERT INTO meeting_acknowledgements (meeting_id, user_id, acknowledged_at, created_at) VALUES (:mid, :uid, :aa, :ca)
             ON DUPLICATE KEY UPDATE acknowledged_at = VALUES(acknowledged_at)',
            ['mid' => (int) $id, 'uid' => $this->userId(), 'aa' => now_utc(), 'ca' => now_utc()]
        );

        Session::flash('success', 'Minutes acknowledged.');
        return $this->redirect('/member/meetings');
    }
}
