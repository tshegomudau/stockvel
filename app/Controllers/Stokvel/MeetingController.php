<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class MeetingController extends Controller
{
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
        return $this->render('stokvel/meetings/create', [
            'title' => 'New Meeting',
            'activeNav' => 'meetings',
            'navContext' => 'app',
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $title = trim((string) $request->input('title', ''));
        $scheduledAt = (string) $request->input('scheduled_at', '');
        $venue = trim((string) $request->input('venue', $request->input('location', '')));
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
        $this->db()->insert(
            'INSERT INTO meetings
                (stokvel_id, meeting_ref, title, meeting_type, status, scheduled_at, venue,
                 created_by_user_id, created_at, updated_at)
             VALUES
                (:sid, :ref, :title, :type, :status, :scheduled_at, :venue, :by, :ca, :ua)',
            [
                'sid' => $sid,
                'ref' => $ref,
                'title' => $title,
                'type' => 'regular',
                'status' => 'scheduled',
                'scheduled_at' => $scheduledAt,
                'venue' => $venue !== '' ? $venue : null,
                'by' => $this->userId(),
                'ca' => $now,
                'ua' => $now,
            ]
        );

        Session::flash('success', 'Meeting saved.');
        return $this->redirect('/app/meetings');
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
        return $this->render('stokvel/meetings/show', [
            'title' => 'Meeting #' . $id,
            'activeNav' => 'meetings',
            'navContext' => 'app',
            'item' => $row,
        ]);
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
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('meetings.manage');
        $sid = $this->requireStokvel();
        $title = trim((string) $request->input('title', ''));
        $scheduledAt = (string) $request->input('scheduled_at', '');
        $venue = trim((string) $request->input('venue', $request->input('location', '')));
        if ($title === '' || $scheduledAt === '') {
            Session::flash('error', 'Title and schedule are required.');
            return $this->redirect('/app/meetings/' . $id . '/edit');
        }
        $scheduledAt = str_replace('T', ' ', $scheduledAt);
        if (strlen($scheduledAt) === 16) {
            $scheduledAt .= ':00';
        }
        $this->db()->exec(
            'UPDATE meetings SET title = :title, scheduled_at = :scheduled_at, venue = :venue, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid',
            [
                'title' => $title,
                'scheduled_at' => $scheduledAt,
                'venue' => $venue !== '' ? $venue : null,
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
}
