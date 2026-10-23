<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

final class ChargeService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_WAIVED = 'waived';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly Database $db,
        private readonly FeeStructureService $feeStructures
    ) {
    }

    public function generateRecurringCharges(int $stokvelId, string $periodDate): array
    {
        $structures = $this->feeStructures->listStructures($stokvelId, true);
        $created = [];

        foreach ($structures as $structure) {
            if ((int) ($structure['is_active'] ?? 0) !== 1) {
                continue;
            }
            $version = $this->feeStructures->getEffectiveVersion($stokvelId, (int) $structure['id'], $periodDate);
            if ($version === null) {
                continue;
            }
            if (!$this->shouldBillOnDate($version, $periodDate)) {
                continue;
            }

            $members = $this->db->fetchAll(
                'SELECT id FROM memberships
                 WHERE stokvel_id = :stokvel_id AND status = :status',
                ['stokvel_id' => $stokvelId, 'status' => 'active']
            );

            foreach ($members as $member) {
                $memberId = (int) $member['id'];
                if ($this->chargeExistsForPeriod($stokvelId, $memberId, (int) $version['id'], $periodDate)) {
                    continue;
                }

                $dueDate = $this->calculateDueDate($periodDate, (int) ($version['grace_days'] ?? $version['grace_period_days'] ?? 0));
                $chargeId = $this->createCharge(
                    $stokvelId,
                    $memberId,
                    (int) $structure['id'],
                    (int) $version['id'],
                    (string) $version['amount'],
                    $dueDate,
                    $periodDate,
                    $periodDate
                );
                $created[] = $chargeId;
            }
        }

        return $created;
    }

    public function createCharge(
        int $stokvelId,
        int $memberId,
        int $feeStructureId,
        int $feeStructureVersionId,
        string $amountSnapshot,
        string $dueDate,
        string $periodStart,
        string $periodEnd
    ): int {
        $version = $this->feeStructures->getVersion($stokvelId, $feeStructureVersionId);
        if ($version === null || (int) $version['fee_structure_id'] !== $feeStructureId) {
            throw new FinanceException('Fee structure version mismatch.');
        }

        $now = now_utc();
        $amount = MoneyMath::normalize($amountSnapshot);

        return (int) $this->db->insert(
            'INSERT INTO member_charges
                (stokvel_id, membership_id, fee_structure_version_id, charge_code, description, amount, amount_paid,
                 due_date, status, period_start, period_end, created_at, updated_at)
             VALUES
                (:stokvel_id, :membership_id, :fee_structure_version_id, :charge_code, :description, :amount, :amount_paid,
                 :due_date, :status, :period_start, :period_end, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'membership_id' => $memberId,
                'fee_structure_version_id' => $feeStructureVersionId,
                'charge_code' => 'CHG-' . $memberId . '-' . str_replace('-', '', $periodStart),
                'description' => 'Recurring charge',
                'amount' => $amount,
                'amount_paid' => '0.00',
                'due_date' => $dueDate,
                'status' => self::STATUS_PENDING,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * Capture historical / manual arrears without a fee structure (migration 016 makes fee version nullable).
     */
    public function createManualArrear(
        int $stokvelId,
        int $membershipId,
        string $amount,
        string $dueDate,
        string $description = 'Historical arrears',
        ?string $periodLabel = null
    ): int {
        $member = $this->db->fetch(
            'SELECT id FROM memberships WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $membershipId, 'sid' => $stokvelId]
        );
        if (!$member) {
            throw new FinanceException('Member not found in this stokvel.');
        }

        $normalized = MoneyMath::normalize($amount);
        if ((float) $normalized <= 0) {
            throw new FinanceException('Arrears amount must be greater than zero.');
        }
        if (strtotime($dueDate) === false) {
            throw new FinanceException('Invalid due date.');
        }

        $now = now_utc();
        $periodStart = $periodLabel && preg_match('/^\d{4}-\d{2}/', $periodLabel)
            ? substr($periodLabel, 0, 7) . '-01'
            : substr($dueDate, 0, 7) . '-01';
        $status = $dueDate < date('Y-m-d') ? self::STATUS_OVERDUE : self::STATUS_PENDING;
        $code = 'ARR-' . $membershipId . '-' . str_replace('-', '', substr($dueDate, 0, 10)) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

        try {
            return (int) $this->db->insert(
                'INSERT INTO member_charges
                    (stokvel_id, membership_id, fee_structure_version_id, charge_code, description, amount, amount_paid,
                     due_date, status, period_start, period_end, created_at, updated_at)
                 VALUES
                    (:stokvel_id, :membership_id, NULL, :charge_code, :description, :amount, :amount_paid,
                     :due_date, :status, :period_start, :period_end, :created_at, :updated_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'membership_id' => $membershipId,
                    'charge_code' => $code,
                    'description' => $description !== '' ? $description : 'Historical arrears',
                    'amount' => $normalized,
                    'amount_paid' => '0.00',
                    'due_date' => $dueDate,
                    'status' => $status,
                    'period_start' => $periodStart,
                    'period_end' => $periodStart,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        } catch (\Throwable $e) {
            throw new FinanceException(
                'Could not save arrears. Run migration 016 so charges can be captured without a fee structure. '
                . $e->getMessage()
            );
        }
    }

    public function markOverdue(int $stokvelId, ?string $asOfDate = null): int
    {
        $asOfDate = $asOfDate ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');

        return $this->db->exec(
            'UPDATE member_charges
             SET status = :overdue, updated_at = :updated_at
             WHERE stokvel_id = :stokvel_id
               AND status IN (\'pending\', \'partial\')
               AND due_date < :as_of_date',
            [
                'overdue' => self::STATUS_OVERDUE,
                'updated_at' => now_utc(),
                'stokvel_id' => $stokvelId,
                'as_of_date' => $asOfDate,
            ]
        );
    }

    public function calculatePenalties(int $stokvelId, ?string $asOfDate = null): int
    {
        // Penalty columns are optional per fee version; skip when schema has no penalty_amount on charges.
        if (!$this->db->fetch("SHOW COLUMNS FROM member_charges LIKE 'penalty_amount'")) {
            return 0;
        }
        $asOfDate = $asOfDate ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $charges = $this->db->fetchAll(
            'SELECT mc.*, fsv.penalty_rate_percent, fsv.penalty_type
             FROM member_charges mc
             INNER JOIN fee_structure_versions fsv ON fsv.id = mc.fee_structure_version_id AND fsv.stokvel_id = mc.stokvel_id
             WHERE mc.stokvel_id = :stokvel_id
               AND mc.status IN (\'overdue\', \'partial\', \'pending\')
               AND mc.due_date < :as_of_date',
            [
                'stokvel_id' => $stokvelId,
                'as_of_date' => $asOfDate,
            ]
        );

        $updated = 0;
        foreach ($charges as $charge) {
            $outstanding = MoneyMath::sub(
                MoneyMath::add((string) $charge['amount'], (string) $charge['penalty_amount']),
                (string) $charge['paid_amount']
            );
            if (!MoneyMath::isPositive($outstanding)) {
                continue;
            }

            $penalty = $this->computePenalty(
                $outstanding,
                (string) $charge['penalty_rate_percent'],
                (string) $charge['penalty_type']
            );
            if (MoneyMath::equals($penalty, (string) $charge['penalty_amount'])) {
                continue;
            }

            $this->db->exec(
                'UPDATE member_charges SET penalty_amount = :penalty, status = :overdue, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'penalty' => $penalty,
                    'overdue' => self::STATUS_OVERDUE,
                    'updated_at' => now_utc(),
                    'id' => $charge['id'],
                    'stokvel_id' => $stokvelId,
                ]
            );
            ++$updated;
        }

        return $updated;
    }

    public function getOutstandingCharges(int $stokvelId, int $memberId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM member_charges
             WHERE stokvel_id = :stokvel_id AND membership_id = :membership_id
               AND status IN (:pending, :partial, :overdue)
             ORDER BY due_date ASC, id ASC',
            [
                'stokvel_id' => $stokvelId,
                'membership_id' => $memberId,
                'pending' => self::STATUS_PENDING,
                'partial' => self::STATUS_PARTIAL,
                'overdue' => self::STATUS_OVERDUE,
            ]
        );
    }

    public function applyAllocation(int $stokvelId, int $chargeId, string $amount): void
    {
        $charge = $this->db->fetch(
            'SELECT * FROM member_charges WHERE id = :id AND stokvel_id = :stokvel_id FOR UPDATE',
            ['id' => $chargeId, 'stokvel_id' => $stokvelId]
        );
        if ($charge === null) {
            throw new FinanceException('Charge not found.');
        }

        $amount = MoneyMath::normalize($amount);
        $totalDue = MoneyMath::normalize((string) $charge['amount']);
        $newPaid = MoneyMath::add((string) ($charge['amount_paid'] ?? $charge['paid_amount'] ?? '0'), $amount);
        if (MoneyMath::comp($newPaid, $totalDue) > 0) {
            throw new FinanceException('Allocation exceeds charge balance.');
        }

        $status = self::STATUS_PARTIAL;
        if (MoneyMath::equals($newPaid, $totalDue)) {
            $status = self::STATUS_PAID;
        }

        $this->db->exec(
            'UPDATE member_charges SET amount_paid = :amount_paid, status = :status, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'amount_paid' => $newPaid,
                'status' => $status,
                'updated_at' => now_utc(),
                'id' => $chargeId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    public function chargeBalance(array $charge): string
    {
        return MoneyMath::sub(
            MoneyMath::normalize((string) $charge['amount']),
            MoneyMath::normalize((string) ($charge['amount_paid'] ?? $charge['paid_amount'] ?? '0'))
        );
    }

    private function computePenalty(string $outstanding, string $ratePercent, string $penaltyType): string
    {
        if ($penaltyType === 'fixed') {
            return MoneyMath::normalize($ratePercent);
        }

        return MoneyMath::div(MoneyMath::mul($outstanding, $ratePercent), '100');
    }

    private function shouldBillOnDate(array $version, string $periodDate): bool
    {
        $frequency = (string) $version['frequency'];
        $day = (int) (new \DateTimeImmutable($periodDate))->format('d');

        return match ($frequency) {
            'daily' => true,
            'weekly' => (new \DateTimeImmutable($periodDate))->format('N') === '1',
            'monthly' => $day === 1,
            'quarterly' => $day === 1 && in_array((new \DateTimeImmutable($periodDate))->format('n'), ['1', '4', '7', '10'], true),
            'annually' => (new \DateTimeImmutable($periodDate))->format('m-d') === '01-01',
            default => $day === 1,
        };
    }

    private function calculateDueDate(string $periodDate, int $graceDays): string
    {
        return (new \DateTimeImmutable($periodDate))->modify('+' . max(0, $graceDays) . ' days')->format('Y-m-d');
    }

    private function chargeExistsForPeriod(int $stokvelId, int $memberId, int $versionId, string $periodDate): bool
    {
        $row = $this->db->fetch(
            'SELECT id FROM member_charges
             WHERE stokvel_id = :stokvel_id AND membership_id = :membership_id
               AND fee_structure_version_id = :version_id AND period_start = :period_start
             LIMIT 1',
            [
                'stokvel_id' => $stokvelId,
                'membership_id' => $memberId,
                'version_id' => $versionId,
                'period_start' => $periodDate,
            ]
        );

        return $row !== null;
    }
}
