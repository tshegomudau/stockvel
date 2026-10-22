<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Exceptions\HttpException;
use App\Services\Meetings\MeetingService;
use App\Support\HtmlSanitizer;

final class MeetingController extends Controller
{
    private const MEETING_TYPES = ['regular', 'agm', 'special', 'committee'];

    public function index(Request $request): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM meetings WHERE stokvel_id = :sid',
            'SELECT * FROM meetings WHERE stokvel_id = :sid ORDER BY scheduled_at DESC',
            ['sid' => $sid],
            $page,
            $perPage,
            url('/app/meetings')
        );
        return $this->render('stokvel/meetings/index', [
            'title' => 'Meetings',
            'activeNav' => 'meetings',
            'navContext' => 'app',
            'paginator' => $paginator,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        return $this->render('stokvel/meetings/create', [
            'title' => 'New Meeting',
            'activeNav' => 'meetings',
            'navContext' => 'app',
            'meetingCategories' => $this->activeMeetingCategories($sid),
            'meetingTypes' => $this->meetingTypeCodes($sid),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $title = trim((string) $request->input('title', ''));
        $scheduledAt = (string) $request->input('scheduled_at', '');
        $venue = trim((string) $request->input('venue', $request->input('location', '')));
        $notes = HtmlSanitizer::clean((string) $request->input('notes', ''));
        $agenda = HtmlSanitizer::clean((string) $request->input('agenda', ''));
        $allowedTypes = $this->meetingTypeCodes($sid);
        $meetingType = strtolower(trim((string) $request->input('meeting_type', 'regular')));
        if (!in_array($meetingType, $allowedTypes, true)) {
            $meetingType = $allowedTypes[0] ?? 'regular';
        }
        $validator = Validator::make(
            ['title' => $title, 'scheduled_at' => $scheduledAt],
            ['title' => 'required|string', 'scheduled_at' => 'required']
        );
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Invalid meeting details.');
            return $this->redirect('/app/meetings/create');
        }

        $scheduledAt = str_replace('T', ' ', $scheduledAt);
        if (strlen($scheduledAt) === 16) {
            $scheduledAt .= ':00';
        }

        $now = now_utc();
        $ref = 'MTG-' . $sid . '-' . gmdate('YmdHis');
        $meetingId = (int) $this->db()->insert(
            'INSERT INTO meetings
                (stokvel_id, meeting_ref, title, meeting_type, status, scheduled_at, venue, notes, agenda,
                 created_by_user_id, created_at, updated_at)
             VALUES
                (:sid, :ref, :title, :type, :status, :scheduled_at, :venue, :notes, :agenda, :by, :ca, :ua)',
            [
                'sid' => $sid,
                'ref' => $ref,
                'title' => $title,
                'type' => $meetingType,
                'status' => 'scheduled',
                'scheduled_at' => $scheduledAt,
                'venue' => $venue !== '' ? $venue : null,
                'notes' => $notes !== '' ? $notes : null,
                'agenda' => $agenda !== '' ? $agenda : null,
                'by' => $this->userId(),
                'ca' => $now,
                'ua' => $now,
            ]
        );
        try {
            $members = $this->db()->fetchAll(
                "SELECT user_id FROM memberships WHERE stokvel_id = :sid AND status = 'active'",
                ['sid' => $sid]
            );
            $nowNotify = now_utc();
            foreach ($members as $m) {
                $this->db()->insert(
                    'INSERT INTO notifications
                        (stokvel_id, user_id, channel, notification_type, title, body, status, created_at, updated_at)
                     VALUES (:sid, :uid, :ch, :type, :title, :body, :st, :ca, :ua)',
                    [
                        'sid' => $sid,
                        'uid' => (int) $m['user_id'],
                        'ch' => 'in_app',
                        'type' => 'meeting',
                        'title' => 'New meeting scheduled',
                        'body' => $title . ' on ' . $scheduledAt,
                        'st' => 'unread',
                        'ca' => $nowNotify,
                        'ua' => $nowNotify,
                    ]
                );
            }
        } catch (\Throwable) {
        }

        Session::flash('success', 'Meeting saved.');
        return $this->redirect('/app/meetings/' . max(1, $meetingId));
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch(
            'SELECT * FROM meetings WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$row) {
            return $this->redirect('/app/meetings');
        }
        $members = $this->db()->fetchAll(
            "SELECT sm.id AS membership_id, p.first_name, p.last_name, sm.member_number,
                    ma.attendance_status, ma.notes AS attendance_notes
             FROM memberships sm
             JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             LEFT JOIN meeting_attendance ma ON ma.membership_id = sm.id AND ma.meeting_id = :mid AND ma.stokvel_id = sm.stokvel_id
             WHERE sm.stokvel_id = :sid AND sm.status = 'active'
             ORDER BY p.last_name, p.first_name",
            ['sid' => $sid, 'mid' => (int) $id]
        );
        $canManageMinutes = $this->tenant()->can('meetings.manage_minutes')
            || $this->tenant()->can('meetings.manage');

        $linkedDocuments = [];
        try {
            $linkedDocuments = $this->db()->fetchAll(
                "SELECT id, title FROM documents
                 WHERE stokvel_id = :sid AND meeting_id = :mid AND status <> 'deleted'
                 ORDER BY title ASC",
                ['sid' => $sid, 'mid' => (int) $id]
            );
        } catch (\Throwable) {
            $linkedDocuments = [];
        }

        return $this->render('stokvel/meetings/show', [
            'title' => (string) ($row['title'] ?? 'Meeting'),
            'activeNav' => 'meetings',
            'navContext' => 'app',
            'item' => $row,
            'members' => $members,
            'canManageMinutes' => $canManageMinutes,
            'linkedDocuments' => $linkedDocuments,
        ]);
    }

    public function saveAttendance(Request $request, string $id): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $statuses = $request->input('attendance', []);
        if (!is_array($statuses)) {
            Session::flash('error', 'Invalid attendance payload.');
            return $this->redirect('/app/meetings/' . $id);
        }
        /** @var MeetingService $meetings */
        $meetings = app()->container()->get(MeetingService::class);
        foreach ($statuses as $membershipId => $status) {
            $status = (string) $status;
            if (!in_array($status, ['present', 'absent', 'apology', 'late'], true)) {
                continue;
            }
            $meetings->recordAttendance($sid, (int) $id, (int) $membershipId, $status);
        }
        Session::flash('success', 'Attendance saved.');
        return $this->redirect('/app/meetings/' . $id);
    }

    public function saveMinutes(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (
            !$this->tenant()->can('meetings.manage_minutes')
            && !$this->tenant()->can('meetings.manage')
        ) {
            throw new HttpException(403, 'You do not have permission to perform this action');
        }
        $sid = $this->requireStokvel();
        $minutes = HtmlSanitizer::clean((string) $request->input('minutes_html', ''));

        /** @var MeetingService $meetings */
        $meetings = app()->container()->get(MeetingService::class);
        $meetings->saveMinutes($sid, (int) $id, (int) $this->userId(), $minutes);

        Session::flash('success', 'Meeting minutes saved.');
        return $this->redirect('/app/meetings/' . $id);
    }

    public function saveNotes(Request $request, string $id): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $notes = HtmlSanitizer::clean((string) $request->input('notes', ''));
        $this->db()->exec(
            'UPDATE meetings SET notes = :notes, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['notes' => $notes !== '' ? $notes : null, 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Meeting notes saved.');
        return $this->redirect('/app/meetings/' . $id);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch(
            'SELECT * FROM meetings WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$row) {
            return $this->redirect('/app/meetings');
        }
        $row['location'] = $row['venue'] ?? '';
        return $this->render('stokvel/meetings/edit', [
            'title' => 'Edit Meeting',
            'activeNav' => 'meetings',
            'navContext' => 'app',
            'item' => $row,
            'meetingCategories' => $this->activeMeetingCategories($sid),
            'meetingTypes' => $this->meetingTypeCodes($sid),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $title = trim((string) $request->input('title', ''));
        $scheduledAt = (string) $request->input('scheduled_at', '');
        $venue = trim((string) $request->input('venue', $request->input('location', '')));
        $notes = HtmlSanitizer::clean((string) $request->input('notes', ''));
        $agenda = HtmlSanitizer::clean((string) $request->input('agenda', ''));
        $allowedTypes = $this->meetingTypeCodes($sid);
        $meetingType = strtolower(trim((string) $request->input('meeting_type', 'regular')));
        if (!in_array($meetingType, $allowedTypes, true)) {
            $meetingType = $allowedTypes[0] ?? 'regular';
        }
        if ($title === '' || $scheduledAt === '') {
            Session::flash('error', 'Title and schedule are required.');
            return $this->redirect('/app/meetings/' . $id . '/edit');
        }
        $scheduledAt = str_replace('T', ' ', $scheduledAt);
        if (strlen($scheduledAt) === 16) {
            $scheduledAt .= ':00';
        }
        $this->db()->exec(
            'UPDATE meetings SET title = :title, meeting_type = :type, scheduled_at = :scheduled_at, venue = :venue,
                    notes = :notes, agenda = :agenda, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid',
            [
                'title' => $title,
                'type' => $meetingType,
                'scheduled_at' => $scheduledAt,
                'venue' => $venue !== '' ? $venue : null,
                'notes' => $notes !== '' ? $notes : null,
                'agenda' => $agenda !== '' ? $agenda : null,
                'ua' => now_utc(),
                'id' => (int) $id,
                'sid' => $sid,
            ]
        );
        Session::flash('success', 'Meeting updated.');
        return $this->redirect('/app/meetings/' . $id);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE meetings SET status = :status, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['status' => 'cancelled', 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Meeting removed.');
        return $this->redirect('/app/meetings');
    }

    /** @return list<array<string, mixed>> */
    private function activeMeetingCategories(int $stokvelId): array
    {
        try {
            return $this->db()->fetchAll(
                'SELECT code, name FROM meeting_categories
                 WHERE stokvel_id = :sid AND is_active = 1
                 ORDER BY name ASC',
                ['sid' => $stokvelId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<string> */
    private function meetingTypeCodes(int $stokvelId): array
    {
        $cats = $this->activeMeetingCategories($stokvelId);
        $codes = array_values(array_filter(array_map(
            static fn (array $row): string => strtolower((string) ($row['code'] ?? '')),
            $cats
        )));
        return $codes !== [] ? $codes : self::MEETING_TYPES;
    }
}
