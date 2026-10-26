<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\TenantException;
use App\Services\Meetings\MeetingService;

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

        /** @var MeetingService $meetings */
        $meetings = app()->container()->get(MeetingService::class);
        $resolutions = $meetings->listResolutions($sid, $meetingId);
        $actionItems = $meetings->listActionItems($sid, $meetingId);

        $membership = $this->activeMembership($sid);
        $membershipId = $membership ? (int) $membership['id'] : null;
        $myVotes = $membershipId
            ? $meetings->votesForMembership(
                $sid,
                $membershipId,
                array_map(static fn (array $r): int => (int) $r['id'], $resolutions)
            )
            : [];

        return $this->render('member/meetings/show', [
            'title' => (string) ($meeting['title'] ?? 'Meeting'),
            'activeNav' => 'member-meetings',
            'navContext' => 'member',
            'meeting' => $meeting,
            'documents' => $documents,
            'resolutions' => $resolutions,
            'actionItems' => $actionItems,
            'myVotes' => $myVotes,
            'currentMembershipId' => $membershipId,
        ]);
    }

    public function acknowledge(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $membership = $this->activeMembership($sid);
        if (!$membership) {
            Session::flash('error', 'Active membership required.');
            return $this->redirect('/member/meetings');
        }

        /** @var MeetingService $meetings */
        $meetings = app()->container()->get(MeetingService::class);
        try {
            $meetings->acknowledgeMinutes($sid, (int) $id, (int) $membership['id']);
            Session::flash('success', 'Minutes acknowledged.');
        } catch (TenantException $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirect('/member/meetings/' . (int) $id);
    }

    public function castVote(Request $request, string $id, string $resolutionId): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $membership = $this->activeMembership($sid);
        if (!$membership) {
            Session::flash('error', 'Active membership required to vote.');
            return $this->redirect('/member/meetings/' . (int) $id);
        }

        $resolution = $this->db()->fetch(
            'SELECT id FROM resolutions WHERE id = :rid AND meeting_id = :mid AND stokvel_id = :sid LIMIT 1',
            ['rid' => (int) $resolutionId, 'mid' => (int) $id, 'sid' => $sid]
        );
        if (!$resolution) {
            Session::flash('error', 'Resolution not found for this meeting.');
            return $this->redirect('/member/meetings/' . (int) $id);
        }

        $vote = strtolower(trim((string) $request->input('vote', '')));

        /** @var MeetingService $meetings */
        $meetings = app()->container()->get(MeetingService::class);
        try {
            $meetings->castVote($sid, (int) $resolutionId, (int) $membership['id'], $vote);
            Session::flash('success', 'Vote recorded.');
        } catch (TenantException $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirect('/member/meetings/' . (int) $id);
    }

    /** @return array<string, mixed>|null */
    private function activeMembership(int $stokvelId): ?array
    {
        $membership = $this->tenant()->membership();
        if ($membership !== null && (int) ($membership['stokvel_id'] ?? 0) === $stokvelId) {
            if (($membership['status'] ?? '') === 'active' || !isset($membership['status'])) {
                return $membership;
            }
        }

        return $this->db()->fetch(
            "SELECT * FROM memberships
             WHERE stokvel_id = :sid AND user_id = :uid AND status = 'active'
             LIMIT 1",
            ['sid' => $stokvelId, 'uid' => (int) $this->userId()]
        );
    }
}
