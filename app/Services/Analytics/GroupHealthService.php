<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Core\Database;
use App\Services\Members\ProfileCompletenessService;
use App\Support\MoneyMath;

/**
 * Transparent operational health score from real tenant data.
 * This is NOT a credit rating or bureau score.
 *
 * @see docs/GROUP_HEALTH.md
 */
final class GroupHealthService
{
    public function __construct(
        private readonly Database $db,
        private readonly ProfileCompletenessService $profiles
    ) {
    }

    /**
     * @return array{
     *   score:int,
     *   band:string,
     *   components:array<string,array{score:?int,weight:int,label:string,detail:string,included:bool}>,
     *   recommendations:list<string>,
     *   disclaimer:string
     * }
     */
    public function score(int $stokvelId): array
    {
        $components = [];
        $components['contribution_collection'] = $this->contributionCollection($stokvelId);
        $components['meeting_attendance'] = $this->meetingAttendance($stokvelId);
        $components['profile_completeness'] = $this->profileCompleteness($stokvelId);
        $components['arrears_pressure'] = $this->arrearsPressure($stokvelId);
        $components['loan_repayment'] = $this->loanRepayment($stokvelId);

        $weightedSum = 0.0;
        $weightTotal = 0;
        foreach ($components as $c) {
            if (!$c['included'] || $c['score'] === null) {
                continue;
            }
            $weightedSum += $c['score'] * $c['weight'];
            $weightTotal += $c['weight'];
        }

        $score = $weightTotal > 0 ? (int) round($weightedSum / $weightTotal) : 0;
        $band = match (true) {
            $score >= 80 => 'Strong',
            $score >= 60 => 'Steady',
            $score >= 40 => 'Needs attention',
            default => 'At risk',
        };

        return [
            'score' => $score,
            'band' => $band,
            'components' => $components,
            'recommendations' => $this->recommendations($components),
            'disclaimer' => 'Operational group health from your stokvel records only. Not a credit rating, bureau score, or lending decision.',
        ];
    }

    /** @return array{score:?int,weight:int,label:string,detail:string,included:bool} */
    private function contributionCollection(int $stokvelId): array
    {
        $periodStart = (new \DateTimeImmutable('first day of this month', new \DateTimeZone('UTC')))->format('Y-m-d');
        $periodEnd = (new \DateTimeImmutable('last day of this month', new \DateTimeZone('UTC')))->format('Y-m-d');

        $due = $this->db->fetch(
            "SELECT COALESCE(SUM(amount), 0) AS due_amt, COALESCE(SUM(amount_paid), 0) AS paid_amt
             FROM member_charges
             WHERE stokvel_id = :sid
               AND due_date BETWEEN :from AND :to
               AND status IN ('pending','partial','overdue','paid','waived')",
            ['sid' => $stokvelId, 'from' => $periodStart, 'to' => $periodEnd]
        );

        $dueAmt = MoneyMath::normalize((string) ($due['due_amt'] ?? '0'));
        $paidAmt = MoneyMath::normalize((string) ($due['paid_amt'] ?? '0'));

        if (!MoneyMath::isPositive($dueAmt)) {
            return [
                'score' => null,
                'weight' => 30,
                'label' => 'Contribution collection',
                'detail' => 'No charges due this calendar month — component skipped.',
                'included' => false,
            ];
        }

        $pct = (float) MoneyMath::div(MoneyMath::mul($paidAmt, '100'), $dueAmt, 4);
        $score = (int) max(0, min(100, round($pct)));

        return [
            'score' => $score,
            'weight' => 30,
            'label' => 'Contribution collection',
            'detail' => sprintf(
                '%s of %s paid on charges due %s–%s (%d%%).',
                money($paidAmt),
                money($dueAmt),
                $periodStart,
                $periodEnd,
                $score
            ),
            'included' => true,
        ];
    }

    /** @return array{score:?int,weight:int,label:string,detail:string,included:bool} */
    private function meetingAttendance(int $stokvelId): array
    {
        $meetings = $this->db->fetchAll(
            "SELECT id FROM meetings
             WHERE stokvel_id = :sid
               AND status IN ('completed','held','closed')
               AND scheduled_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 180 DAY)
             ORDER BY scheduled_at DESC
             LIMIT 6",
            ['sid' => $stokvelId]
        );

        if ($meetings === []) {
            return [
                'score' => null,
                'weight' => 20,
                'label' => 'Meeting attendance',
                'detail' => 'No completed meetings in the last 180 days — component skipped.',
                'included' => false,
            ];
        }

        $ids = array_map(static fn ($m) => (int) $m['id'], $meetings);
        $placeholders = [];
        $params = ['sid' => $stokvelId];
        foreach ($ids as $i => $id) {
            $key = 'm' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $stats = $this->db->fetch(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN attendance_status IN (\'present\',\'attended\',\'late\') THEN 1 ELSE 0 END) AS present
             FROM meeting_attendance
             WHERE stokvel_id = :sid AND meeting_id IN (' . implode(',', $placeholders) . ')',
            $params
        );

        $total = (int) ($stats['total'] ?? 0);
        if ($total < 1) {
            return [
                'score' => null,
                'weight' => 20,
                'label' => 'Meeting attendance',
                'detail' => 'Attendance not recorded for recent meetings — component skipped.',
                'included' => false,
            ];
        }

        $present = (int) ($stats['present'] ?? 0);
        $score = (int) round(($present / $total) * 100);

        return [
            'score' => $score,
            'weight' => 20,
            'label' => 'Meeting attendance',
            'detail' => sprintf('%d of %d attendance marks present/late across %d recent meetings (%d%%).', $present, $total, count($ids), $score),
            'included' => true,
        ];
    }

    /** @return array{score:?int,weight:int,label:string,detail:string,included:bool} */
    private function profileCompleteness(int $stokvelId): array
    {
        $avg = $this->profiles->averageForStokvel($stokvelId);
        if ($avg['member_count'] < 1) {
            return [
                'score' => null,
                'weight' => 15,
                'label' => 'Profile completeness',
                'detail' => 'No active members — component skipped.',
                'included' => false,
            ];
        }

        $score = (int) round($avg['average_percent']);

        return [
            'score' => $score,
            'weight' => 15,
            'label' => 'Profile completeness',
            'detail' => sprintf('Average %d%% across %d active members.', $score, $avg['member_count']),
            'included' => true,
        ];
    }

    /** @return array{score:?int,weight:int,label:string,detail:string,included:bool} */
    private function arrearsPressure(int $stokvelId): array
    {
        $row = $this->db->fetch(
            "SELECT
                COALESCE(SUM(amount), 0) AS total_due,
                COALESCE(SUM(GREATEST(amount - amount_paid, 0)), 0) AS outstanding
             FROM member_charges
             WHERE stokvel_id = :sid
               AND status IN ('pending','partial','overdue','paid')",
            ['sid' => $stokvelId]
        );

        $totalDue = MoneyMath::normalize((string) ($row['total_due'] ?? '0'));
        $outstanding = MoneyMath::normalize((string) ($row['outstanding'] ?? '0'));

        if (!MoneyMath::isPositive($totalDue)) {
            return [
                'score' => 100,
                'weight' => 20,
                'label' => 'Arrears pressure',
                'detail' => 'No open contribution charges on record.',
                'included' => true,
            ];
        }

        $outstandingPct = (float) MoneyMath::div(MoneyMath::mul($outstanding, '100'), $totalDue, 4);
        $score = (int) max(0, min(100, round(100 - $outstandingPct)));

        return [
            'score' => $score,
            'weight' => 20,
            'label' => 'Arrears pressure',
            'detail' => sprintf(
                '%s outstanding of %s charged (inverse pressure score %d).',
                money($outstanding),
                money($totalDue),
                $score
            ),
            'included' => true,
        ];
    }

    /** @return array{score:?int,weight:int,label:string,detail:string,included:bool} */
    private function loanRepayment(int $stokvelId): array
    {
        $loansExist = (int) ($this->db->fetch(
            'SELECT COUNT(*) AS c FROM loans WHERE stokvel_id = :sid',
            ['sid' => $stokvelId]
        )['c'] ?? 0);

        if ($loansExist < 1) {
            return [
                'score' => null,
                'weight' => 15,
                'label' => 'Loan repayment currency',
                'detail' => 'No loans on record — component skipped.',
                'included' => false,
            ];
        }

        $sched = $this->db->fetch(
            "SELECT
                COALESCE(SUM(total_due), 0) AS due_amt,
                COALESCE(SUM(amount_paid), 0) AS paid_amt
             FROM loan_schedules
             WHERE stokvel_id = :sid AND due_date <= CURDATE()",
            ['sid' => $stokvelId]
        );

        $dueAmt = MoneyMath::normalize((string) ($sched['due_amt'] ?? '0'));
        $paidAmt = MoneyMath::normalize((string) ($sched['paid_amt'] ?? '0'));

        if (!MoneyMath::isPositive($dueAmt)) {
            return [
                'score' => 100,
                'weight' => 15,
                'label' => 'Loan repayment currency',
                'detail' => 'Loans exist but no instalments are due yet.',
                'included' => true,
            ];
        }

        $pct = (float) MoneyMath::div(MoneyMath::mul($paidAmt, '100'), $dueAmt, 4);
        $score = (int) max(0, min(100, round($pct)));

        return [
            'score' => $score,
            'weight' => 15,
            'label' => 'Loan repayment currency',
            'detail' => sprintf('%s of %s paid on instalments due to date (%d%%).', money($paidAmt), money($dueAmt), $score),
            'included' => true,
        ];
    }

    /**
     * @param array<string,array{score:?int,weight:int,label:string,detail:string,included:bool}> $components
     * @return list<string>
     */
    private function recommendations(array $components): array
    {
        $out = [];
        foreach ($components as $key => $c) {
            if (!$c['included'] || $c['score'] === null) {
                continue;
            }
            if ($c['score'] >= 70) {
                continue;
            }
            $out[] = match ($key) {
                'contribution_collection' => 'Follow up on unpaid charges due this month (' . $c['detail'] . ')',
                'meeting_attendance' => 'Improve attendance capture and follow up absences (' . $c['detail'] . ')',
                'profile_completeness' => 'Ask members to complete missing profile fields (' . $c['detail'] . ')',
                'arrears_pressure' => 'Prioritise arrears recovery (' . $c['detail'] . ')',
                'loan_repayment' => 'Chase overdue loan instalments (' . $c['detail'] . ')',
                default => $c['label'] . ' needs attention.',
            };
        }
        if ($out === []) {
            $out[] = 'No urgent actions from current metrics — keep recording contributions, attendance, and repayments.';
        }
        return $out;
    }
}
