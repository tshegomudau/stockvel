<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

/**
 * Configurable automatic fines only where data is unambiguous.
 * Always preview before apply; idempotent keys prevent duplicates.
 */
final class AutoFineService
{
    public const RULE_LATE_CONTRIBUTION = 'late_contribution';
    public const RULE_MISSED_MEETING = 'missed_meeting';

    public function __construct(
        private readonly Database $db,
        private readonly FineService $fines
    ) {
    }

    /**
     * @return array{
     *   rule:string,
     *   enabled:bool,
     *   fine_type:?array<string,mixed>,
     *   candidates:list<array<string,mixed>>,
     *   count:int,
     *   total_amount:string,
     *   summary:string
     * }
     */
    public function preview(int $stokvelId, string $rule): array
    {
        $rule = $this->normalizeRule($rule);
        $type = $this->resolveAutomaticType($stokvelId, $rule);
        if ($type === null) {
            return [
                'rule' => $rule,
                'enabled' => false,
                'fine_type' => null,
                'candidates' => [],
                'count' => 0,
                'total_amount' => '0.00',
                'summary' => 'No active automatic-eligible fine type configured for this rule. Enable a fine type with code "' . $rule . '" (or matching rule) and mark it automatic-eligible.',
            ];
        }

        $candidates = match ($rule) {
            self::RULE_LATE_CONTRIBUTION => $this->lateContributionCandidates($stokvelId, $type),
            self::RULE_MISSED_MEETING => $this->missedMeetingCandidates($stokvelId, $type),
            default => [],
        };

        $total = '0.00';
        foreach ($candidates as $c) {
            $total = MoneyMath::add($total, (string) ($c['amount'] ?? '0'));
        }

        $amountLabel = money((float) ($type['default_amount'] ?? 0));
        $summary = match ($rule) {
            self::RULE_LATE_CONTRIBUTION => sprintf(
                '%d member(s) will receive a %s late contribution fine.',
                count($candidates),
                $amountLabel
            ),
            self::RULE_MISSED_MEETING => sprintf(
                '%d member(s) will receive a %s missed-meeting fine (attendance finalised, no accepted apology).',
                count($candidates),
                $amountLabel
            ),
            default => count($candidates) . ' fine(s) would be issued.',
        };

        return [
            'rule' => $rule,
            'enabled' => true,
            'fine_type' => $type,
            'candidates' => $candidates,
            'count' => count($candidates),
            'total_amount' => $total,
            'summary' => $summary,
        ];
    }

    /**
     * Apply previewed candidates. Requires confirm=1 style confirmation at controller.
     *
     * @return array{issued:int,skipped:int,fine_ids:list<int>}
     */
    public function apply(int $stokvelId, string $rule, int $issuedByUserId): array
    {
        $preview = $this->preview($stokvelId, $rule);
        if (!$preview['enabled'] || $preview['count'] < 1) {
            return ['issued' => 0, 'skipped' => 0, 'fine_ids' => []];
        }

        $type = $preview['fine_type'];
        if ($type === null) {
            throw new FinanceException('Automatic fine type missing.');
        }

        $fineIds = [];
        $issued = 0;
        $skipped = 0;
        foreach ($preview['candidates'] as $candidate) {
            $key = (string) ($candidate['idempotency_key'] ?? '');
            if ($key === '') {
                $skipped++;
                continue;
            }
            $id = $this->fines->issue(
                $stokvelId,
                (int) $candidate['membership_id'],
                (int) $type['id'],
                (string) ($candidate['amount'] ?? $type['default_amount']),
                $issuedByUserId,
                [
                    'reason' => (string) ($candidate['reason'] ?? ''),
                    'due_date' => $candidate['due_date'] ?? null,
                    'source_type' => (string) ($candidate['source_type'] ?? 'auto_fine'),
                    'source_id' => isset($candidate['source_id']) ? (int) $candidate['source_id'] : null,
                    'idempotency_key' => $key,
                ]
            );
            $fineIds[] = $id;
            $issued++;
        }

        return ['issued' => $issued, 'skipped' => $skipped, 'fine_ids' => $fineIds];
    }

    private function normalizeRule(string $rule): string
    {
        $rule = strtolower(trim($rule));
        return match ($rule) {
            'late', 'overdue', 'late_contribution', 'contribution_overdue' => self::RULE_LATE_CONTRIBUTION,
            'absence', 'missed', 'missed_meeting', 'meeting_absence' => self::RULE_MISSED_MEETING,
            default => $rule,
        };
    }

    /** @return array<string, mixed>|null */
    private function resolveAutomaticType(int $stokvelId, string $rule): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM fine_types
             WHERE stokvel_id = :sid AND is_active = 1 AND is_automatic_eligible = 1
               AND (code = :code OR code = :alt)
             ORDER BY display_order ASC, id ASC LIMIT 1',
            [
                'sid' => $stokvelId,
                'code' => $rule,
                'alt' => $rule === self::RULE_LATE_CONTRIBUTION ? 'late_payment' : 'absence',
            ]
        );
        if ($row) {
            return $row;
        }

        // Fallback: any automatic-eligible type whose name/code mentions the rule keywords
        $all = $this->db->fetchAll(
            'SELECT * FROM fine_types
             WHERE stokvel_id = :sid AND is_active = 1 AND is_automatic_eligible = 1
             ORDER BY display_order ASC, id ASC',
            ['sid' => $stokvelId]
        );
        foreach ($all as $type) {
            $hay = strtolower((string) ($type['code'] ?? '') . ' ' . (string) ($type['name'] ?? ''));
            if ($rule === self::RULE_LATE_CONTRIBUTION
                && (str_contains($hay, 'late') || str_contains($hay, 'overdue') || str_contains($hay, 'contribution'))) {
                return $type;
            }
            if ($rule === self::RULE_MISSED_MEETING
                && (str_contains($hay, 'miss') || str_contains($hay, 'absent') || str_contains($hay, 'meeting'))) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $type
     * @return list<array<string, mixed>>
     */
    private function lateContributionCandidates(int $stokvelId, array $type): array
    {
        $settings = $this->db->fetch(
            'SELECT grace_period_days FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        );
        $grace = max(0, (int) ($settings['grace_period_days'] ?? 7));
        $cutoff = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))
            ->modify('-' . $grace . ' days')
            ->format('Y-m-d');

        $charges = $this->db->fetchAll(
            "SELECT mc.id, mc.membership_id, mc.due_date, mc.amount, mc.amount_paid,
                    TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) AS member_name
             FROM member_charges mc
             JOIN memberships m ON m.id = mc.membership_id AND m.stokvel_id = mc.stokvel_id
             JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE mc.stokvel_id = :sid
               AND m.status = 'active'
               AND mc.status IN ('pending','partial','overdue')
               AND (mc.amount - mc.amount_paid) > 0.009
               AND mc.due_date <= :cutoff
             ORDER BY mc.due_date ASC, mc.id ASC",
            ['sid' => $stokvelId, 'cutoff' => $cutoff]
        );

        $amount = MoneyMath::normalize((string) ($type['default_amount'] ?? '0'));
        $out = [];
        foreach ($charges as $charge) {
            $chargeId = (int) $charge['id'];
            $mid = (int) $charge['membership_id'];
            $key = 'autofine:late_contrib:' . $chargeId;
            $existing = $this->db->fetch(
                'SELECT id FROM fines WHERE stokvel_id = :sid AND idempotency_key = :k LIMIT 1',
                ['sid' => $stokvelId, 'k' => $key]
            );
            if ($existing) {
                continue;
            }
            $out[] = [
                'membership_id' => $mid,
                'member_name' => $charge['member_name'] ?? '',
                'amount' => $amount,
                'reason' => 'Late contribution after grace period (charge #' . $chargeId . ', due ' . ($charge['due_date'] ?? '') . ')',
                'due_date' => (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->modify('+7 days')->format('Y-m-d'),
                'source_type' => 'member_charge',
                'source_id' => $chargeId,
                'idempotency_key' => $key,
                'detail' => 'Due ' . ($charge['due_date'] ?? '') . ' · outstanding ' . money((float) $charge['amount'] - (float) $charge['amount_paid']),
            ];
        }

        return $out;
    }

    /**
     * Only when attendance is captured (at least one mark) and status is absent without apology.
     *
     * @param array<string, mixed> $type
     * @return list<array<string, mixed>>
     */
    private function missedMeetingCandidates(int $stokvelId, array $type): array
    {
        $meetings = $this->db->fetchAll(
            "SELECT id, title, scheduled_at FROM meetings
             WHERE stokvel_id = :sid
               AND status IN ('completed','held','closed')
               AND scheduled_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)
             ORDER BY scheduled_at DESC
             LIMIT 20",
            ['sid' => $stokvelId]
        );

        $amount = MoneyMath::normalize((string) ($type['default_amount'] ?? '0'));
        $out = [];
        foreach ($meetings as $meeting) {
            $meetingId = (int) $meeting['id'];
            $marked = (int) ($this->db->fetch(
                'SELECT COUNT(*) AS c FROM meeting_attendance WHERE stokvel_id = :sid AND meeting_id = :mid',
                ['sid' => $stokvelId, 'mid' => $meetingId]
            )['c'] ?? 0);
            if ($marked < 1) {
                // Attendance not finalised — skip ambiguous meetings
                continue;
            }

            $absentees = $this->db->fetchAll(
                "SELECT ma.membership_id,
                        TRIM(CONCAT(COALESCE(p.first_name,''), ' ', COALESCE(p.last_name,''))) AS member_name
                 FROM meeting_attendance ma
                 JOIN memberships m ON m.id = ma.membership_id AND m.stokvel_id = ma.stokvel_id
                 JOIN users u ON u.id = m.user_id
                 LEFT JOIN user_profiles p ON p.user_id = u.id
                 WHERE ma.stokvel_id = :sid AND ma.meeting_id = :mid
                   AND ma.attendance_status = 'absent'
                   AND m.status = 'active'",
                ['sid' => $stokvelId, 'mid' => $meetingId]
            );

            foreach ($absentees as $row) {
                $mid = (int) $row['membership_id'];
                $key = 'autofine:absence:' . $meetingId . ':' . $mid;
                $existing = $this->db->fetch(
                    'SELECT id FROM fines WHERE stokvel_id = :sid AND idempotency_key = :k LIMIT 1',
                    ['sid' => $stokvelId, 'k' => $key]
                );
                if ($existing) {
                    continue;
                }
                $out[] = [
                    'membership_id' => $mid,
                    'member_name' => $row['member_name'] ?? '',
                    'amount' => $amount,
                    'reason' => 'Missed meeting without apology: ' . ($meeting['title'] ?? ('#' . $meetingId)),
                    'due_date' => (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->modify('+7 days')->format('Y-m-d'),
                    'source_type' => 'meeting',
                    'source_id' => $meetingId,
                    'idempotency_key' => $key,
                    'detail' => (string) ($meeting['title'] ?? '') . ' · ' . sa_datetime((string) ($meeting['scheduled_at'] ?? '')),
                ];
            }
        }

        return $out;
    }
}
