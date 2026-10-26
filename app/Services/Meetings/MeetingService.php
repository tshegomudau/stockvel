<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use App\Core\Database;
use App\Exceptions\TenantException;
use App\Services\AuditService;
use App\Services\Notifications\NotificationService;

final class MeetingService
{
    private const OPEN_STATUSES = ['open', 'proposed'];

    private const VOTE_MAP = [
        'yes' => 'yes',
        'for' => 'yes',
        'no' => 'no',
        'against' => 'no',
        'abstain' => 'abstain',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly ?AuditService $audit = null,
        private readonly ?NotificationService $notifications = null,
    ) {
    }

    public function list(int $stokvelId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM meetings WHERE stokvel_id = ? ORDER BY scheduled_at DESC LIMIT ?',
            [$stokvelId, $limit]
        );
    }

    public function find(int $stokvelId, int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM meetings WHERE stokvel_id = ? AND id = ?',
            [$stokvelId, $id]
        );
    }

    public function schedule(int $stokvelId, int $userId, array $data): int
    {
        $now = now_utc();
        $ref = $data['meeting_ref'] ?? ('MTG-' . $stokvelId . '-' . gmdate('YmdHis'));
        $id = (int) $this->db->insert(
            'INSERT INTO meetings
                (stokvel_id, meeting_ref, title, meeting_type, status, scheduled_at, venue, virtual_link,
                 notes, created_by_user_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $stokvelId,
                $ref,
                $data['title'],
                $data['meeting_type'] ?? 'regular',
                'scheduled',
                $data['scheduled_at'],
                $data['venue'] ?? null,
                $data['online_link'] ?? ($data['virtual_link'] ?? null),
                $data['description'] ?? ($data['notes'] ?? null),
                $userId,
                $now,
                $now,
            ]
        );

        if (!empty($data['agenda']) && is_array($data['agenda'])) {
            foreach ($data['agenda'] as $i => $item) {
                $this->db->insert(
                    'INSERT INTO meeting_agendas
                        (stokvel_id, meeting_id, item_number, title, description, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [
                        $stokvelId,
                        $id,
                        $i + 1,
                        $item['title'],
                        $item['notes'] ?? ($item['description'] ?? null),
                        $now,
                        $now,
                    ]
                );
            }
        }

        $this->audit?->log(
            'meeting.scheduled',
            'meeting',
            $id,
            null,
            ['title' => $data['title']],
            null,
            $stokvelId,
            $userId
        );

        return $id;
    }

    public function recordAttendance(int $stokvelId, int $meetingId, int $membershipId, string $status, ?string $apology = null): void
    {
        $meeting = $this->find($stokvelId, $meetingId);
        if (!$meeting) {
            throw new TenantException('Meeting not found.');
        }
        $existing = $this->db->fetch(
            'SELECT id FROM meeting_attendance WHERE stokvel_id = ? AND meeting_id = ? AND membership_id = ?',
            [$stokvelId, $meetingId, $membershipId]
        );
        $notes = $apology;
        if ($existing) {
            $this->db->exec(
                'UPDATE meeting_attendance
                 SET attendance_status = ?, notes = ?, recorded_at = ?
                 WHERE id = ? AND stokvel_id = ?',
                [$status, $notes, now_utc(), $existing['id'], $stokvelId]
            );
        } else {
            $this->db->insert(
                'INSERT INTO meeting_attendance
                    (stokvel_id, meeting_id, membership_id, attendance_status, recorded_at, notes, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$stokvelId, $meetingId, $membershipId, $status, now_utc(), $notes, now_utc()]
            );
        }
    }

    public function saveMinutes(int $stokvelId, int $meetingId, int $userId, string $minutes): void
    {
        $meeting = $this->find($stokvelId, $meetingId);
        if (!$meeting) {
            throw new TenantException('Meeting not found.');
        }

        $body = $minutes !== '' ? $minutes : null;
        $this->db->exec(
            'UPDATE meetings SET minutes_html = ?, updated_at = ? WHERE id = ? AND stokvel_id = ?',
            [$body, now_utc(), $meetingId, $stokvelId]
        );

        // Keep legacy meeting_minutes in sync when the table exists.
        try {
            $existing = $this->db->fetch(
                'SELECT id FROM meeting_minutes WHERE stokvel_id = ? AND meeting_id = ?',
                [$stokvelId, $meetingId]
            );
            $content = $body ?? '';
            if ($existing) {
                $this->db->exec(
                    'UPDATE meeting_minutes
                     SET content = ?, recorded_by_user_id = ?, updated_at = ?
                     WHERE id = ? AND stokvel_id = ?',
                    [$content, $userId, now_utc(), $existing['id'], $stokvelId]
                );
            } else {
                $this->db->insert(
                    'INSERT INTO meeting_minutes
                        (stokvel_id, meeting_id, content, status, recorded_by_user_id, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$stokvelId, $meetingId, $content, 'draft', $userId, now_utc(), now_utc()]
                );
            }
        } catch (\Throwable) {
            // Legacy table optional — primary store is meetings.minutes_html.
        }

        $this->audit?->log(
            'meeting.minutes_saved',
            'meeting',
            $meetingId,
            null,
            null,
            null,
            $stokvelId,
            $userId
        );
    }

    public function addResolution(int $stokvelId, int $meetingId, int $userId, string $title, string $body): int
    {
        $meeting = $this->find($stokvelId, $meetingId);
        if (!$meeting) {
            throw new TenantException('Meeting not found.');
        }

        $title = trim($title);
        $body = trim($body);
        if ($title === '' || $body === '') {
            throw new TenantException('Resolution title and body are required.');
        }

        $number = $this->nextResolutionNumber($stokvelId);
        $now = now_utc();

        $id = (int) $this->db->insert(
            'INSERT INTO resolutions
                (stokvel_id, meeting_id, resolution_number, title, description, status,
                 votes_for, votes_against, votes_abstain, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?)',
            [$stokvelId, $meetingId, $number, $title, $body, 'open', $now, $now]
        );

        $this->audit?->log(
            'meeting.resolution_added',
            'resolution',
            $id,
            null,
            ['title' => $title, 'meeting_id' => $meetingId],
            null,
            $stokvelId,
            $userId
        );

        return $id;
    }

    public function castVote(int $stokvelId, int $resolutionId, int $membershipId, string $vote): void
    {
        $normalized = self::VOTE_MAP[strtolower(trim($vote))] ?? null;
        if ($normalized === null) {
            throw new TenantException('Invalid vote. Use yes, no, or abstain.');
        }

        $membership = $this->db->fetch(
            "SELECT id FROM memberships WHERE id = ? AND stokvel_id = ? AND status = 'active'",
            [$membershipId, $stokvelId]
        );
        if (!$membership) {
            throw new TenantException('Membership not found.');
        }

        $resolution = $this->db->fetch(
            'SELECT id, status, meeting_id FROM resolutions WHERE stokvel_id = ? AND id = ?',
            [$stokvelId, $resolutionId]
        );
        if (!$resolution) {
            throw new TenantException('Resolution not found.');
        }
        if (!in_array((string) $resolution['status'], self::OPEN_STATUSES, true)) {
            throw new TenantException('Voting is closed for this resolution.');
        }

        $existing = $this->db->fetch(
            'SELECT id, vote_value FROM votes WHERE stokvel_id = ? AND resolution_id = ? AND membership_id = ?',
            [$stokvelId, $resolutionId, $membershipId]
        );

        $now = now_utc();
        if ($existing) {
            $previous = (string) ($existing['vote_value'] ?? '');
            if ($previous === $normalized) {
                return;
            }
            $this->db->exec(
                'UPDATE votes SET vote_value = ?, cast_at = ? WHERE id = ? AND stokvel_id = ?',
                [$normalized, $now, $existing['id'], $stokvelId]
            );
            $this->adjustVoteCounters($stokvelId, $resolutionId, $previous, -1);
            $this->adjustVoteCounters($stokvelId, $resolutionId, $normalized, 1);
        } else {
            $this->db->insert(
                'INSERT INTO votes (stokvel_id, resolution_id, membership_id, vote_value, cast_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$stokvelId, $resolutionId, $membershipId, $normalized, $now, $now]
            );
            $this->adjustVoteCounters($stokvelId, $resolutionId, $normalized, 1);
        }
    }

    public function addActionItem(int $stokvelId, int $meetingId, int $userId, array $data): int
    {
        $meeting = $this->find($stokvelId, $meetingId);
        if (!$meeting) {
            throw new TenantException('Meeting not found.');
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new TenantException('Action item title is required.');
        }

        $assignedId = $data['assigned_membership_id'] ?? $data['assigned_to_membership_id'] ?? null;
        $assignedId = $assignedId !== null && $assignedId !== '' ? (int) $assignedId : null;
        if ($assignedId !== null && $assignedId > 0) {
            $member = $this->db->fetch(
                "SELECT id FROM memberships WHERE id = ? AND stokvel_id = ? AND status = 'active'",
                [$assignedId, $stokvelId]
            );
            if (!$member) {
                throw new TenantException('Assignee membership not found.');
            }
        } else {
            $assignedId = null;
        }

        $dueDate = $data['due_date'] ?? null;
        if (is_string($dueDate) && trim($dueDate) === '') {
            $dueDate = null;
        }

        $now = now_utc();
        $id = (int) $this->db->insert(
            'INSERT INTO action_items
                (stokvel_id, meeting_id, title, description, assigned_to_membership_id, due_date, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $stokvelId,
                $meetingId,
                $title,
                $data['description'] ?? null,
                $assignedId,
                $dueDate,
                'open',
                $now,
                $now,
            ]
        );

        $this->audit?->log(
            'meeting.action_item_added',
            'action_item',
            $id,
            null,
            ['title' => $title, 'meeting_id' => $meetingId],
            null,
            $stokvelId,
            $userId
        );

        return $id;
    }

    public function acknowledgeMinutes(int $stokvelId, int $meetingId, int $membershipId): void
    {
        $meeting = $this->find($stokvelId, $meetingId);
        if (!$meeting) {
            throw new TenantException('Meeting not found.');
        }
        if (empty($meeting['minutes_html'])) {
            throw new TenantException('Minutes not found.');
        }

        $membership = $this->db->fetch(
            'SELECT id, user_id FROM memberships WHERE id = ? AND stokvel_id = ?',
            [$membershipId, $stokvelId]
        );
        if (!$membership) {
            throw new TenantException('Membership not found.');
        }

        $userId = (int) $membership['user_id'];
        $now = now_utc();
        $existing = $this->db->fetch(
            'SELECT id FROM meeting_acknowledgements
             WHERE stokvel_id = ? AND meeting_id = ? AND user_id = ?',
            [$stokvelId, $meetingId, $userId]
        );
        if (!$existing) {
            $this->db->insert(
                'INSERT INTO meeting_acknowledgements
                    (stokvel_id, meeting_id, user_id, acknowledged_at, created_at)
                 VALUES (?, ?, ?, ?, ?)',
                [$stokvelId, $meetingId, $userId, $now, $now]
            );
        }
    }

    /** @return list<array<string, mixed>> */
    public function listResolutions(int $stokvelId, int $meetingId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM resolutions
             WHERE stokvel_id = ? AND meeting_id = ?
             ORDER BY id ASC',
            [$stokvelId, $meetingId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function listActionItems(int $stokvelId, int $meetingId): array
    {
        return $this->db->fetchAll(
            "SELECT ai.*,
                    TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS assignee_name,
                    m.member_number AS assignee_number
             FROM action_items ai
             LEFT JOIN memberships m ON m.id = ai.assigned_to_membership_id AND m.stokvel_id = ai.stokvel_id
             LEFT JOIN user_profiles p ON p.user_id = m.user_id
             WHERE ai.stokvel_id = ? AND ai.meeting_id = ?
             ORDER BY ai.due_date IS NULL, ai.due_date ASC, ai.id ASC",
            [$stokvelId, $meetingId]
        );
    }

    public function voteForMembership(int $stokvelId, int $resolutionId, int $membershipId): ?string
    {
        $row = $this->db->fetch(
            'SELECT vote_value FROM votes
             WHERE stokvel_id = ? AND resolution_id = ? AND membership_id = ?
             LIMIT 1',
            [$stokvelId, $resolutionId, $membershipId]
        );
        return $row ? (string) $row['vote_value'] : null;
    }

    /** @return array<int, string> resolution_id => vote_value */
    public function votesForMembership(int $stokvelId, int $membershipId, array $resolutionIds): array
    {
        if ($resolutionIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($resolutionIds), '?'));
        $params = array_merge([$stokvelId, $membershipId], array_map('intval', $resolutionIds));
        $rows = $this->db->fetchAll(
            "SELECT resolution_id, vote_value FROM votes
             WHERE stokvel_id = ? AND membership_id = ? AND resolution_id IN ($placeholders)",
            $params
        );
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['resolution_id']] = (string) $row['vote_value'];
        }
        return $out;
    }

    private function nextResolutionNumber(int $stokvelId): string
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS c FROM resolutions WHERE stokvel_id = ?',
            [$stokvelId]
        );
        $n = ((int) ($row['c'] ?? 0)) + 1;
        return 'RES-' . $stokvelId . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function adjustVoteCounters(int $stokvelId, int $resolutionId, string $vote, int $delta): void
    {
        $column = match ($vote) {
            'yes', 'for' => 'votes_for',
            'no', 'against' => 'votes_against',
            'abstain' => 'votes_abstain',
            default => null,
        };
        if ($column === null || $delta === 0) {
            return;
        }
        $op = $delta > 0 ? '+' : '-';
        $abs = abs($delta);
        $this->db->exec(
            "UPDATE resolutions
             SET {$column} = GREATEST(0, CAST({$column} AS SIGNED) {$op} ?), updated_at = ?
             WHERE id = ? AND stokvel_id = ?",
            [$abs, now_utc(), $resolutionId, $stokvelId]
        );
    }
}
