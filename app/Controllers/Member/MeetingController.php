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
            'SELECT m.id, m.title, m.scheduled_at, m.status, m.venue, m.minutes_html, ma.acknowledged_at
             FROM meetings m
             LEFT JOIN meeting_acknowledgements ma
               ON ma.meeting_id = m.id AND ma.user_id = :uid AND ma.stokvel_id = m.stokvel_id
             WHERE m.stokvel_id = :sid
             ORDER BY m.scheduled_at DESC
             LIMIT 50',
            ['sid' => $sid, 'uid' => $this->userId()]
        );

        return $this->render('member/meetings/index', [
            'title' => 'Meetings',
            'activeNav' => 'member-meetings',
            'navContext' => 'member',
            'meetings' => $meetings,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $meetingId = (int) $id;

        $meeting = $this->db()->fetch(
            'SELECT m.*, ma.acknowledged_at
             FROM meetings m
             LEFT JOIN meeting_acknowledgements ma
               ON ma.meeting_id = m.id AND ma.user_id = :uid AND ma.stokvel_id = m.stokvel_id
             WHERE m.id = :id AND m.stokvel_id = :sid
             LIMIT 1',
            ['id' => $meetingId, 'sid' => $sid, 'uid' => $this->userId()]
        );
        if (!$meeting) {
            Session::flash('error', 'Meeting not found.');
            return $this->redirect('/member/meetings');
        }

        $documents = [];
        try {
            $documents = $this->db()->fetchAll(
                "SELECT d.id, d.title, d.category, d.content_type, dc.name AS category_name
                 FROM documents d
                 LEFT JOIN document_categories dc ON dc.id = d.document_category_id AND dc.stokvel_id = d.stokvel_id
                 WHERE d.stokvel_id = :sid AND d.meeting_id = :mid AND d.status = 'active'
                 ORDER BY d.title ASC",
                ['sid' => $sid, 'mid' => $meetingId]
            );
        } catch (\Throwable) {
            $documents = [];
        }

        return $this->render('member/meetings/show', [
            'title' => (string) ($meeting['title'] ?? 'Meeting'),
            'activeNav' => 'member-meetings',
            'navContext' => 'member',
            'meeting' => $meeting,
            'documents' => $documents,
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
            'INSERT INTO meeting_acknowledgements (stokvel_id, meeting_id, user_id, acknowledged_at, created_at)
             VALUES (:sid, :mid, :uid, :aa, :ca)
             ON DUPLICATE KEY UPDATE acknowledged_at = VALUES(acknowledged_at)',
            [
                'sid' => $sid,
                'mid' => (int) $id,
                'uid' => $this->userId(),
                'aa' => now_utc(),
                'ca' => now_utc(),
            ]
        );

        Session::flash('success', 'Minutes acknowledged.');
        return $this->redirect('/member/meetings/' . (int) $id);
    }
}
