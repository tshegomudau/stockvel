<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Core\Database;

/**
 * Descriptive analytics and simple transparent forecasting from real history.
 * Forecasts are labelled projections — not guarantees.
 */
final class TrendsService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return array{
     *   months:list<array{label:string,year:int,month:int,collected:float,expected:float,arrears:float,expenses:float,members_joined:int,attendance_pct:?float,fines_collected:float}>,
     *   forecast:list<array{label:string,projected_collected:float,method:string}>,
     *   disclaimer:string
     * }
     */
    public function monthlySeries(int $stokvelId, int $months = 6): array
    {
        $months = max(3, min(24, $months));
        $series = [];
        $collectedTrail = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = (new \DateTimeImmutable('first day of this month', new \DateTimeZone('UTC')))->modify("-{$i} months");
            $y = (int) $month->format('Y');
            $m = (int) $month->format('n');
            $from = $month->format('Y-m-01');
            $to = $month->modify('last day of this month')->format('Y-m-d');

            $collected = (float) ($this->db->fetch(
                "SELECT COALESCE(SUM(amount),0) AS t FROM payment_transactions
                 WHERE stokvel_id = :sid
                   AND status IN ('posted','completed','allocated','confirmed')
                   AND MONTH(paid_at) = :m AND YEAR(paid_at) = :y",
                ['sid' => $stokvelId, 'm' => $m, 'y' => $y]
            )['t'] ?? 0);

            $expected = (float) ($this->db->fetch(
                "SELECT COALESCE(SUM(amount),0) AS t FROM member_charges
                 WHERE stokvel_id = :sid AND MONTH(due_date) = :m AND YEAR(due_date) = :y",
                ['sid' => $stokvelId, 'm' => $m, 'y' => $y]
            )['t'] ?? 0);

            $arrears = (float) ($this->db->fetch(
                "SELECT COALESCE(SUM(amount - amount_paid),0) AS t FROM member_charges
                 WHERE stokvel_id = :sid AND status IN ('pending','partial','overdue')
                   AND due_date <= :to",
                ['sid' => $stokvelId, 'to' => $to]
            )['t'] ?? 0);

            $expenses = $this->sumExpenses($stokvelId, $from, $to);

            $joined = (int) ($this->db->fetch(
                "SELECT COUNT(*) AS c FROM memberships
                 WHERE stokvel_id = :sid AND DATE(joined_at) BETWEEN :from AND :to",
                ['sid' => $stokvelId, 'from' => $from, 'to' => $to]
            )['c'] ?? 0);

            $finesCollected = (float) ($this->db->fetch(
                "SELECT COALESCE(SUM(amount_paid),0) AS t FROM fines
                 WHERE stokvel_id = :sid AND DATE(updated_at) BETWEEN :from AND :to
                   AND amount_paid > 0",
                ['sid' => $stokvelId, 'from' => $from, 'to' => $to]
            )['t'] ?? 0);

            $attendancePct = $this->attendanceForMonth($stokvelId, $from, $to);

            $series[] = [
                'label' => $month->format('M Y'),
                'year' => $y,
                'month' => $m,
                'collected' => round($collected, 2),
                'expected' => round($expected, 2),
                'arrears' => round($arrears, 2),
                'expenses' => round($expenses, 2),
                'members_joined' => $joined,
                'attendance_pct' => $attendancePct,
                'fines_collected' => round($finesCollected, 2),
            ];
            $collectedTrail[] = $collected;
        }

        $forecast = $this->trailingAverageForecast($collectedTrail, 3);

        return [
            'months' => $series,
            'forecast' => $forecast,
            'disclaimer' => 'Forecast figures are simple trailing averages of recorded history. They are projections only — not guaranteed results.',
        ];
    }

    /**
     * Annual management summary for a financial year window.
     *
     * @return array<string, float|int|string>
     */
    public function annualSummary(int $stokvelId, string $from, string $to): array
    {
        $contributions = (float) ($this->db->fetch(
            "SELECT COALESCE(SUM(amount),0) AS t FROM payment_transactions
             WHERE stokvel_id = :sid AND status IN ('posted','completed','allocated','confirmed')
               AND DATE(paid_at) BETWEEN :from AND :to",
            ['sid' => $stokvelId, 'from' => $from, 'to' => $to]
        )['t'] ?? 0);

        $adminFees = (float) ($this->db->fetch(
            "SELECT COALESCE(SUM(amount_paid),0) AS t FROM member_charges
             WHERE stokvel_id = :sid
               AND (charge_code LIKE 'admin%' OR LOWER(description) LIKE '%admin%')
               AND due_date BETWEEN :from AND :to",
            ['sid' => $stokvelId, 'from' => $from, 'to' => $to]
        )['t'] ?? 0);

        $expenses = $this->sumExpenses($stokvelId, $from, $to);

        $outstanding = (float) ($this->db->fetch(
            "SELECT COALESCE(SUM(amount - amount_paid),0) AS t FROM member_charges
             WHERE stokvel_id = :sid AND status IN ('pending','partial','overdue')",
            ['sid' => $stokvelId]
        )['t'] ?? 0);

        $loansIssued = (float) ($this->db->fetch(
            "SELECT COALESCE(SUM(principal_amount),0) AS t FROM loans
             WHERE stokvel_id = :sid AND DATE(COALESCE(disbursed_at, created_at)) BETWEEN :from AND :to",
            ['sid' => $stokvelId, 'from' => $from, 'to' => $to]
        )['t'] ?? 0);

        $loansOutstanding = (float) ($this->db->fetch(
            "SELECT COALESCE(SUM(outstanding_balance),0) AS t FROM loans
             WHERE stokvel_id = :sid AND status IN ('active','disbursed','arrears')",
            ['sid' => $stokvelId]
        )['t'] ?? 0);

        $repayments = (float) ($this->db->fetch(
            "SELECT COALESCE(SUM(amount),0) AS t FROM loan_repayments
             WHERE stokvel_id = :sid AND DATE(paid_at) BETWEEN :from AND :to",
            ['sid' => $stokvelId, 'from' => $from, 'to' => $to]
        )['t'] ?? 0);

        $payouts = (float) ($this->db->fetch(
            "SELECT COALESCE(SUM(payout_amount),0) AS t FROM payout_schedule
             WHERE stokvel_id = :sid AND status IN ('paid','completed')
               AND DATE(COALESCE(paid_at, scheduled_date)) BETWEEN :from AND :to",
            ['sid' => $stokvelId, 'from' => $from, 'to' => $to]
        )['t'] ?? 0);

        $fines = (float) ($this->db->fetch(
            "SELECT COALESCE(SUM(amount),0) AS t FROM fines
             WHERE stokvel_id = :sid AND DATE(issued_at) BETWEEN :from AND :to
               AND status NOT IN ('cancelled')",
            ['sid' => $stokvelId, 'from' => $from, 'to' => $to]
        )['t'] ?? 0);

        $memberCount = (int) ($this->db->fetch(
            "SELECT COUNT(*) AS c FROM memberships WHERE stokvel_id = :sid AND status = 'active'",
            ['sid' => $stokvelId]
        )['c'] ?? 0);

        $meetingsHeld = (int) ($this->db->fetch(
            "SELECT COUNT(*) AS c FROM meetings
             WHERE stokvel_id = :sid AND status IN ('completed','held','closed')
               AND DATE(scheduled_at) BETWEEN :from AND :to",
            ['sid' => $stokvelId, 'from' => $from, 'to' => $to]
        )['c'] ?? 0);

        $attendancePct = $this->attendanceForMonth($stokvelId, $from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'total_contributions' => round($contributions, 2),
            'total_admin_fees' => round($adminFees, 2),
            'total_expenses' => round($expenses, 2),
            'outstanding_contributions' => round($outstanding, 2),
            'loans_issued' => round($loansIssued, 2),
            'loans_outstanding' => round($loansOutstanding, 2),
            'repayments' => round($repayments, 2),
            'payouts' => round($payouts, 2),
            'fines' => round($fines, 2),
            'member_count' => $memberCount,
            'meetings_held' => $meetingsHeld,
            'average_attendance' => $attendancePct,
            'label' => 'Annual stokvel management summary',
            'disclaimer' => 'Management report from application records. Not an audited financial statement.',
        ];
    }

    /**
     * @param list<float> $trail
     * @return list<array{label:string,projected_collected:float,method:string}>
     */
    private function trailingAverageForecast(array $trail, int $ahead): array
    {
        $window = array_slice($trail, -6);
        if ($window === []) {
            return [];
        }
        $avg = array_sum($window) / count($window);
        $out = [];
        $cursor = new \DateTimeImmutable('first day of next month', new \DateTimeZone('UTC'));
        for ($i = 0; $i < $ahead; $i++) {
            $label = $cursor->modify("+{$i} months")->format('M Y');
            $out[] = [
                'label' => $label,
                'projected_collected' => round($avg, 2),
                'method' => 'Trailing ' . count($window) . '-month average of recorded collections',
            ];
        }
        return $out;
    }

    private function attendanceForMonth(int $stokvelId, string $from, string $to): ?float
    {
        $stats = $this->db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN ma.attendance_status IN ('present','late','attended') THEN 1 ELSE 0 END) AS present
             FROM meeting_attendance ma
             JOIN meetings mt ON mt.id = ma.meeting_id AND mt.stokvel_id = ma.stokvel_id
             WHERE ma.stokvel_id = :sid
               AND DATE(mt.scheduled_at) BETWEEN :from AND :to",
            ['sid' => $stokvelId, 'from' => $from, 'to' => $to]
        );
        $total = (int) ($stats['total'] ?? 0);
        if ($total < 1) {
            return null;
        }
        return round(((int) ($stats['present'] ?? 0) / $total) * 100, 1);
    }

    private function sumExpenses(int $stokvelId, string $from, string $to): float
    {
        try {
            return (float) ($this->db->fetch(
                "SELECT COALESCE(SUM(amount),0) AS t FROM expenses
                 WHERE stokvel_id = :sid AND status IN ('approved','paid','posted')
                   AND DATE(COALESCE(incurred_at, created_at)) BETWEEN :from AND :to",
                ['sid' => $stokvelId, 'from' => $from, 'to' => $to]
            )['t'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
