<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();
        $this->promoteDueScheduled($sid);

        $roleCodes = $this->memberRoleCodes($sid, $userId);
        $items = $this->db()->fetchAll(
            "SELECT a.*,
                    ar.read_at,
                    TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS author_name
             FROM announcements a
             LEFT JOIN announcement_reads ar
                ON ar.announcement_id = a.id AND ar.user_id = :uid
             LEFT JOIN users u ON u.id = a.created_by_user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE a.stokvel_id = :sid
               AND a.status = 'published'
               AND (a.expire_at IS NULL OR a.expire_at > :now)
             ORDER BY a.publish_at DESC, a.id DESC",
            ['sid' => $sid, 'uid' => $userId, 'now' => now_utc()]
        );

        $visible = [];
        foreach ($items as $item) {
            if ($this->audienceAllows((string) ($item['audience'] ?? 'all'), $roleCodes)) {
                $visible[] = $item;
            }
        }

        return $this->render('member/announcements/index', [
            'title' => 'Announcements',
            'activeNav' => 'member-announcements',
            'navContext' => 'member',
            'items' => $visible,
        ]);
    }

    public function markRead(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();
        $this->promoteDueScheduled($sid);

        $item = $this->db()->fetch(
            "SELECT id, audience FROM announcements
             WHERE id = :id AND stokvel_id = :sid AND status = 'published' LIMIT 1",
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$item || !$this->audienceAllows((string) ($item['audience'] ?? 'all'), $this->memberRoleCodes($sid, $userId))) {
            Session::flash('error', 'Announcement not found.');
            return $this->redirect('/member/announcements');
        }

        $existing = $this->db()->fetch(
            'SELECT id FROM announcement_reads WHERE announcement_id = :aid AND user_id = :uid LIMIT 1',
            ['aid' => (int) $id, 'uid' => $userId]
        );
        if (!$existing) {
            $this->db()->insert(
                'INSERT INTO announcement_reads (stokvel_id, announcement_id, user_id, read_at)
                 VALUES (:sid, :aid, :uid, :ra)',
                [
                    'sid' => $sid,
                    'aid' => (int) $id,
                    'uid' => $userId,
                    'ra' => now_utc(),
                ]
            );
        }

        Session::flash('success', 'Marked as read.');
        return $this->redirect('/member/announcements');
    }

    private function promoteDueScheduled(int $stokvelId): void
    {
        $this->db()->exec(
            "UPDATE announcements
             SET status = 'published', updated_at = :ua
             WHERE stokvel_id = :sid AND status = 'scheduled'
               AND publish_at IS NOT NULL AND publish_at <= :now",
            ['ua' => now_utc(), 'sid' => $stokvelId, 'now' => now_utc()]
        );
    }

    /** @return list<string> */
    private function memberRoleCodes(int $stokvelId, int $userId): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT r.code
             FROM memberships m
             INNER JOIN membership_roles mr ON mr.membership_id = m.id AND mr.stokvel_id = m.stokvel_id
             INNER JOIN roles r ON r.id = mr.role_id
             WHERE m.stokvel_id = :sid AND m.user_id = :uid AND m.status = \'active\'',
            ['sid' => $stokvelId, 'uid' => $userId]
        );
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (array $r): string => strtolower((string) ($r['code'] ?? '')),
            $rows
        ))));

        // Memberships without explicit role rows are ordinary members.
        if ($codes === []) {
            $membership = $this->db()->fetch(
                'SELECT id FROM memberships WHERE stokvel_id = :sid AND user_id = :uid AND status = \'active\' LIMIT 1',
                ['sid' => $stokvelId, 'uid' => $userId]
            );
            if ($membership) {
                $codes[] = 'ordinary_member';
            }
        }

        return $codes;
    }

    /** @param list<string> $roleCodes */
    private function audienceAllows(string $audience, array $roleCodes): bool
    {
        if ($audience === '' || $audience === 'all' || $audience === 'members') {
            return true;
        }
        if ($audience === 'admins') {
            return count(array_intersect($roleCodes, [
                'chairperson', 'treasurer', 'secretary', 'committee_member', 'committee',
            ])) > 0;
        }
        $decoded = json_decode($audience, true);
        if (!is_array($decoded)) {
            return false;
        }
        $wanted = array_map(static fn ($v) => strtolower(trim((string) $v)), $decoded);

        return count(array_intersect($wanted, $roleCodes)) > 0;
    }
}
