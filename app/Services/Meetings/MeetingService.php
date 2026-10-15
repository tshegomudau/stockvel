<?php

declare(strict_types=1);

namespace App\Services\Meetings;

use App\Core\Database;
use App\Exceptions\TenantException;
use App\Services\AuditService;
use App\Services\Notifications\NotificationService;

final class MeetingService
{
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
        $id = (int) $this->db->insert(
            'INSERT INTO meetings (stokvel_id, title, description, venue, online_link, scheduled_at, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $stokvelId,
                $data['title'],
                $data['description'] ?? null,
                $data['venue'] ?? null,
                $data['online_link'] ?? null,
                $data['scheduled_at'],
                'scheduled',
                $userId,
                now_utc(),
                now_utc(),
            ]
        );

        if (!empty($data['agenda']) && is_array($data['agenda'])) {
            foreach ($data['agenda'] as $i => $item) {
                $this->db->insert(
                    'INSERT INTO meeting_agendas (stokvel_id, meeting_id, sort_order, title, notes, created_at)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$stokvelId, $id, $i + 1, $item['title'], $item['notes'] ?? null, now_utc()]
                );
            }
        }

        $this->audit?->log($stokvelId, $userId, 'meeting.scheduled', 'meeting', $id, null, ['title' => $data['title']]);
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
        if ($existing) {
            $this->db->exec(
                'UPDATE meeting_attendance SET status = ?, apology_reason = ?, updated_at = ? WHERE id = ? AND stokvel_id = ?',
                [$status, $apology, now_utc(), $existing['id'], $stokvelId]
            );
        } else {
            $this->db->insert(
                'INSERT INTO meeting_attendance (stokvel_id, meeting_id, membership_id, status, apology_reason, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$stokvelId, $meetingId, $membershipId, $status, $apology, now_utc(), now_utc()]
            );
        }
    }

    public function saveMinutes(int $stokvelId, int $meetingId, int $userId, string $minutes): void
    {
        $meeting = $this->find($stokvelId, $meetingId);
        if (!$meeting) {
            throw new TenantException('Meeting not found.');
        }
        $existing = $this->db->fetch(
            'SELECT id FROM meeting_minutes WHERE stokvel_id = ? AND meeting_id = ?',
            [$stokvelId, $meetingId]
        );
        if ($existing) {
            $this->db->exec(
                'UPDATE meeting_minutes SET body = ?, updated_by = ?, updated_at = ? WHERE id = ? AND stokvel_id = ?',
                [$minutes, $userId, now_utc(), $existing['id'], $stokvelId]
            );
        } else {
            $this->db->insert(
                'INSERT INTO meeting_minutes (stokvel_id, meeting_id, body, created_by, updated_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$stokvelId, $meetingId, $minutes, $userId, $userId, now_utc(), now_utc()]
            );
        }
        $this->audit?->log($stokvelId, $userId, 'meeting.minutes_saved', 'meeting', $meetingId, null, null);
    }

    public function addResolution(int $stokvelId, int $meetingId, int $userId, string $title, string $body): int
    {
        return (int) $this->db->insert(
            'INSERT INTO resolutions (stokvel_id, meeting_id, title, body, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$stokvelId, $meetingId, $title, $body, 'open', $userId, now_utc(), now_utc()]
        );
    }

    public function castVote(int $stokvelId, int $resolutionId, int $membershipId, string $vote): void
    {
        $resolution = $this->db->fetch(
            'SELECT id FROM resolutions WHERE stokvel_id = ? AND id = ?',
            [$stokvelId, $resolutionId]
        );
        if (!$resolution) {
            throw new TenantException('Resolution not found.');
        }
        $existing = $this->db->fetch(
            'SELECT id FROM votes WHERE stokvel_id = ? AND resolution_id = ? AND membership_id = ?',
            [$stokvelId, $resolutionId, $membershipId]
        );
        if ($existing) {
            $this->db->exec(
                'UPDATE votes SET vote = ?, updated_at = ? WHERE id = ? AND stokvel_id = ?',
                [$vote, now_utc(), $existing['id'], $stokvelId]
            );
        } else {
            $this->db->insert(
                'INSERT INTO votes (stokvel_id, resolution_id, membership_id, vote, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$stokvelId, $resolutionId, $membershipId, $vote, now_utc(), now_utc()]
            );
        }
    }

    public function addActionItem(int $stokvelId, int $meetingId, int $userId, array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO action_items (stokvel_id, meeting_id, title, description, assigned_membership_id, due_date, status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $stokvelId,
                $meetingId,
                $data['title'],
                $data['description'] ?? null,
                $data['assigned_membership_id'] ?? null,
                $data['due_date'] ?? null,
                'open',
                $userId,
                now_utc(),
                now_utc(),
            ]
        );
    }

    public function acknowledgeMinutes(int $stokvelId, int $meetingId, int $membershipId): void
    {
        $minutes = $this->db->fetch(
            'SELECT id FROM meeting_minutes WHERE stokvel_id = ? AND meeting_id = ?',
            [$stokvelId, $meetingId]
        );
        if (!$minutes) {
            throw new TenantException('Minutes not found.');
        }
        $existing = $this->db->fetch(
            'SELECT id FROM meeting_minute_acknowledgements WHERE stokvel_id = ? AND meeting_id = ? AND membership_id = ?',
            [$stokvelId, $meetingId, $membershipId]
        );
        if (!$existing) {
            $this->db->insert(
                'INSERT INTO meeting_minute_acknowledgements (stokvel_id, meeting_id, membership_id, acknowledged_at, created_at)
                 VALUES (?, ?, ?, ?, ?)',
                [$stokvelId, $meetingId, $membershipId, now_utc(), now_utc()]
            );
        }
    }
}
