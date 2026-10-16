<?php

declare(strict_types=1);

namespace App\Services\Payouts;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Exceptions\FinanceException;
use App\Services\Finance\ChartOfAccountsService;
use App\Services\Finance\LedgerService;
use App\Support\MoneyMath;

final class PayoutService
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_POSTPONED = 'postponed';
    public const STATUS_REVERSED = 'reversed';

    public function __construct(
        private readonly Database $db,
        private readonly LedgerService $ledger,
        private readonly ChartOfAccountsService $chartOfAccounts
    ) {
    }

    public function createRotationCycle(
        int $stokvelId,
        string $name,
        string $cycleStartDate,
        ?string $cycleEndDate,
        int $createdBy
    ): int {
        $now = now_utc();
        $code = 'CYC-' . $stokvelId . '-' . gmdate('YmdHis');
        return (int) $this->db->insert(
            'INSERT INTO payout_cycles
                (stokvel_id, cycle_code, name, status, start_date, end_date, created_at, updated_at)
             VALUES
                (:stokvel_id, :cycle_code, :name, :status, :start_date, :end_date, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'cycle_code' => $code,
                'name' => $name,
                'status' => 'planning',
                'start_date' => $cycleStartDate,
                'end_date' => $cycleEndDate,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function scheduleMemberPayout(
        int $stokvelId,
        int $cycleId,
        int $membershipId,
        string $amount,
        string $scheduledDate,
        int $sequenceNumber = 1
    ): int {
        $cycle = $this->getCycle($stokvelId, $cycleId);
        if ($cycle === null) {
            throw new FinanceException('Payout cycle not found.');
        }
        $member = $this->db->fetch(
            'SELECT id FROM memberships WHERE id = :id AND stokvel_id = :stokvel_id',
            ['id' => $membershipId, 'stokvel_id' => $stokvelId]
        );
        if ($member === null) {
            throw new FinanceException('Invalid member for payout schedule.');
        }

        $now = now_utc();
        $scheduleId = (int) $this->db->insert(
            'INSERT INTO payout_schedule
                (stokvel_id, payout_cycle_id, membership_id, sequence_number, scheduled_date,
                 payout_amount, status, created_at, updated_at)
             VALUES
                (:stokvel_id, :payout_cycle_id, :membership_id, :sequence_number, :scheduled_date,
                 :payout_amount, :status, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'payout_cycle_id' => $cycleId,
                'membership_id' => $membershipId,
                'sequence_number' => $sequenceNumber,
                'scheduled_date' => $scheduledDate,
                'payout_amount' => MoneyMath::normalize($amount),
                'status' => 'scheduled',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $this->db->exec(
            'UPDATE payout_cycles SET payout_amount = :amount, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid',
            [
                'amount' => MoneyMath::normalize($amount),
                'ua' => $now,
                'id' => $cycleId,
                'sid' => $stokvelId,
            ]
        );

        return $scheduleId;
    }

    /** @param list<int> $memberIdsInOrder */
    public function setBeneficiaryOrder(int $stokvelId, int $cycleId, array $memberIdsInOrder): void
    {
        $cycle = $this->getCycle($stokvelId, $cycleId);
        if ($cycle === null) {
            throw new FinanceException('Payout cycle not found.');
        }

        $amount = MoneyMath::normalize((string) ($cycle['payout_amount'] ?? '0'));
        $start = (string) ($cycle['start_date'] ?? date('Y-m-d'));

        $this->db->transaction(function () use ($stokvelId, $cycleId, $memberIdsInOrder, $amount, $start): void {
            $this->db->exec(
                'DELETE FROM payout_schedule WHERE stokvel_id = :stokvel_id AND payout_cycle_id = :cycle_id',
                ['stokvel_id' => $stokvelId, 'cycle_id' => $cycleId]
            );

            $position = 1;
            foreach ($memberIdsInOrder as $memberId) {
                $member = $this->db->fetch(
                    'SELECT id FROM memberships WHERE id = :id AND stokvel_id = :stokvel_id',
                    ['id' => $memberId, 'stokvel_id' => $stokvelId]
                );
                if ($member === null) {
                    throw new FinanceException('Invalid member in rotation order.');
                }

                $scheduledDate = (new \DateTimeImmutable($start))
                    ->modify('+' . ($position - 1) . ' month')
                    ->format('Y-m-d');

                $this->db->insert(
                    'INSERT INTO payout_schedule
                        (stokvel_id, payout_cycle_id, membership_id, sequence_number, scheduled_date,
                         payout_amount, status, created_at, updated_at)
                     VALUES
                        (:stokvel_id, :payout_cycle_id, :membership_id, :sequence_number, :scheduled_date,
                         :payout_amount, :status, :created_at, :updated_at)',
                    [
                        'stokvel_id' => $stokvelId,
                        'payout_cycle_id' => $cycleId,
                        'membership_id' => $memberId,
                        'sequence_number' => $position,
                        'scheduled_date' => $scheduledDate,
                        'payout_amount' => $amount,
                        'status' => 'scheduled',
                        'created_at' => now_utc(),
                        'updated_at' => now_utc(),
                    ]
                );
                ++$position;
            }
        });
    }

    public function checkEligibility(int $stokvelId, int $cycleId, int $memberId): array
    {
        $member = $this->db->fetch(
            'SELECT m.*, COALESCE(SUM(CASE WHEN mc.status IN (\'pending\',\'partial\',\'overdue\') THEN 1 ELSE 0 END), 0) AS open_charges
             FROM memberships m
             LEFT JOIN member_charges mc ON mc.membership_id = m.id AND mc.stokvel_id = m.stokvel_id
             WHERE m.id = :member_id AND m.stokvel_id = :stokvel_id
             GROUP BY m.id',
            ['member_id' => $memberId, 'stokvel_id' => $stokvelId]
        );
        if ($member === null) {
            throw new FinanceException('Member not found.');
        }

        $reasons = [];
        if (($member['status'] ?? '') !== 'active') {
            $reasons[] = 'Member is not active.';
        }
        if ((int) ($member['open_charges'] ?? 0) > 0) {
            $reasons[] = 'Member has outstanding contribution charges.';
        }

        $loanArrears = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM loans
             WHERE stokvel_id = :stokvel_id AND membership_id = :member_id AND status = :arrears_status',
            ['stokvel_id' => $stokvelId, 'member_id' => $memberId, 'arrears_status' => 'arrears']
        );
        if ($loanArrears !== null && (int) $loanArrears['cnt'] > 0) {
            $reasons[] = 'Member has loan arrears.';
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
        ];
    }

    public function previewEndOfCycleDistribution(int $stokvelId, int $cycleId): array
    {
        $cycle = $this->getCycle($stokvelId, $cycleId);
        if ($cycle === null) {
            throw new FinanceException('Payout cycle not found.');
        }

        $pool = $this->calculateDistributionPool($stokvelId, $cycle);
        $beneficiaries = $this->db->fetchAll(
            'SELECT ps.*, m.member_number, p.first_name, p.last_name
             FROM payout_schedule ps
             INNER JOIN memberships m ON m.id = ps.membership_id AND m.stokvel_id = ps.stokvel_id
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE ps.stokvel_id = :stokvel_id AND ps.payout_cycle_id = :cycle_id
             ORDER BY ps.sequence_number ASC',
            ['stokvel_id' => $stokvelId, 'cycle_id' => $cycleId]
        );

        $eligible = [];
        foreach ($beneficiaries as $row) {
            $memberId = (int) ($row['membership_id'] ?? $row['member_id'] ?? 0);
            $check = $this->checkEligibility($stokvelId, $cycleId, $memberId);
            if ($check['eligible'] ?? true) {
                $row['_member_id'] = $memberId;
                $eligible[] = $row;
            }
        }

        $count = count($eligible);
        $share = $count > 0 ? MoneyMath::div($pool, (string) $count) : '0.00';
        $lines = [];
        foreach ($eligible as $row) {
            $lines[] = [
                'member_id' => (int) $row['_member_id'],
                'member_number' => (string) $row['member_number'],
                'name' => trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')),
                'position' => (int) ($row['sequence_number'] ?? $row['position'] ?? 0),
                'amount' => $share,
            ];
        }

        return [
            'cycle_id' => $cycleId,
            'pool_amount' => $pool,
            'beneficiary_count' => $count,
            'lines' => $lines,
        ];
    }

    public function approveCycle(int $stokvelId, int $cycleId, int $approvedBy): void
    {
        $cycle = $this->getCycle($stokvelId, $cycleId);
        if ($cycle === null) {
            throw new FinanceException('Payout cycle not found.');
        }

        $this->db->exec(
            'UPDATE payout_cycles SET status = :status, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => 'approved',
                'updated_at' => now_utc(),
                'id' => $cycleId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    public function calculateEndOfCycleDistribution(int $stokvelId, int $cycleId): array
    {
        return $this->previewEndOfCycleDistribution($stokvelId, $cycleId);
    }

    public function recordPayout(
        int $stokvelId,
        int $cycleId,
        int $memberId,
        string $amount,
        int $recordedBy,
        ?string $payoutDate = null
    ): int {
        return (int) $this->db->transaction(function () use ($stokvelId, $cycleId, $memberId, $amount, $recordedBy, $payoutDate) {
            $cycle = $this->getCycle($stokvelId, $cycleId);
            if ($cycle === null || !in_array($cycle['status'], ['approved', 'planning', self::STATUS_APPROVED, self::STATUS_PLANNED], true)) {
                throw new FinanceException('Payout cycle must be approved or planning before recording payouts.');
            }

            $eligibility = $this->checkEligibility($stokvelId, $cycleId, $memberId);
            if (!$eligibility['eligible']) {
                throw new FinanceException('Member is not eligible: ' . implode(' ', $eligibility['reasons']));
            }

            $amount = MoneyMath::normalize($amount);
            $payoutDate = $payoutDate ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
            $now = now_utc();

            $schedule = $this->db->fetch(
                'SELECT id FROM payout_schedule
                 WHERE stokvel_id = :sid AND payout_cycle_id = :cid AND membership_id = :mid
                 ORDER BY sequence_number ASC LIMIT 1',
                ['sid' => $stokvelId, 'cid' => $cycleId, 'mid' => $memberId]
            );
            if ($schedule === null) {
                throw new FinanceException('No payout schedule entry for this member.');
            }

            $this->db->exec(
                'UPDATE payout_schedule SET status = :status, paid_at = :paid_at, payout_amount = :amount, updated_at = :ua
                 WHERE id = :id AND stokvel_id = :sid',
                [
                    'status' => 'paid',
                    'paid_at' => $now,
                    'amount' => $amount,
                    'ua' => $now,
                    'id' => $schedule['id'],
                    'sid' => $stokvelId,
                ]
            );

            $distributionId = (int) $this->db->insert(
                'INSERT INTO distributions
                    (stokvel_id, payout_cycle_id, payout_schedule_id, distribution_ref, amount, status,
                     paid_at, created_at, updated_at)
                 VALUES
                    (:stokvel_id, :payout_cycle_id, :payout_schedule_id, :distribution_ref, :amount, :status,
                     :paid_at, :created_at, :updated_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'payout_cycle_id' => $cycleId,
                    'payout_schedule_id' => (int) $schedule['id'],
                    'distribution_ref' => 'DIST-' . $stokvelId . '-' . (int) $schedule['id'],
                    'amount' => $amount,
                    'status' => 'paid',
                    'paid_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $bank = $this->chartOfAccounts->requireBySubType($stokvelId, 'bank');
            $payoutExpense = $this->chartOfAccounts->requireBySubType($stokvelId, 'payout');

            $journalId = $this->ledger->createDraft(
                $stokvelId,
                $recordedBy,
                $payoutDate,
                'PAYOUT-' . $distributionId,
                'Rotational payout #' . $distributionId
            );
            $this->ledger->addLine($stokvelId, $journalId, (int) $payoutExpense['id'], $amount, '0.00', null, 'Payout expense');
            $this->ledger->addLine($stokvelId, $journalId, (int) $bank['id'], '0.00', $amount, null, 'Bank outflow');
            $this->ledger->post($stokvelId, $journalId, $recordedBy, true);

            return $distributionId;
        });
    }

    public function skip(int $stokvelId, int $cycleId, int $memberId, int $actionBy, string $reason): void
    {
        $this->recordRotationAction($stokvelId, $cycleId, $memberId, self::STATUS_SKIPPED, $actionBy, $reason);
    }

    public function postpone(int $stokvelId, int $cycleId, int $memberId, int $actionBy, string $reason, string $newDate): void
    {
        $this->recordRotationAction($stokvelId, $cycleId, $memberId, self::STATUS_POSTPONED, $actionBy, $reason, $newDate);
    }

    public function swap(
        int $stokvelId,
        int $cycleId,
        int $memberAId,
        int $memberBId,
        int $actionBy,
        string $reason
    ): void {
        $this->db->transaction(function () use ($stokvelId, $cycleId, $memberAId, $memberBId): void {
            $a = $this->db->fetch(
                'SELECT * FROM payout_schedule
                 WHERE stokvel_id = :stokvel_id AND payout_cycle_id = :cycle_id AND membership_id = :member_id FOR UPDATE',
                ['stokvel_id' => $stokvelId, 'cycle_id' => $cycleId, 'member_id' => $memberAId]
            );
            $b = $this->db->fetch(
                'SELECT * FROM payout_schedule
                 WHERE stokvel_id = :stokvel_id AND payout_cycle_id = :cycle_id AND membership_id = :member_id FOR UPDATE',
                ['stokvel_id' => $stokvelId, 'cycle_id' => $cycleId, 'member_id' => $memberBId]
            );
            if ($a === null || $b === null) {
                throw new FinanceException('Both members must exist in the rotation order.');
            }

            $seqA = (int) $a['sequence_number'];
            $seqB = (int) $b['sequence_number'];
            $dateA = (string) $a['scheduled_date'];
            $dateB = (string) $b['scheduled_date'];

            $this->db->exec(
                'UPDATE payout_schedule SET sequence_number = :sequence_number, scheduled_date = :scheduled_date, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'sequence_number' => $seqB,
                    'scheduled_date' => $dateB,
                    'updated_at' => now_utc(),
                    'id' => $a['id'],
                    'stokvel_id' => $stokvelId,
                ]
            );
            $this->db->exec(
                'UPDATE payout_schedule SET sequence_number = :sequence_number, scheduled_date = :scheduled_date, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'sequence_number' => $seqA,
                    'scheduled_date' => $dateA,
                    'updated_at' => now_utc(),
                    'id' => $b['id'],
                    'stokvel_id' => $stokvelId,
                ]
            );
        });
    }

    public function reversePayout(int $stokvelId, int $distributionId, int $reversedBy, string $reason): void
    {
        $record = $this->db->fetch(
            'SELECT * FROM distributions WHERE id = :id AND stokvel_id = :stokvel_id',
            ['id' => $distributionId, 'stokvel_id' => $stokvelId]
        );
        if ($record === null || !in_array($record['status'], ['paid', self::STATUS_PAID], true)) {
            throw new FinanceException('Paid distribution not found.');
        }

        $this->db->exec(
            'UPDATE distributions SET status = :status, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => self::STATUS_REVERSED,
                'updated_at' => now_utc(),
                'id' => $distributionId,
                'stokvel_id' => $stokvelId,
            ]
        );

        if (!empty($record['payout_schedule_id'])) {
            $this->db->exec(
                'UPDATE payout_schedule SET status = :status, paid_at = NULL, notes = :notes, updated_at = :ua
                 WHERE id = :id AND stokvel_id = :sid',
                [
                    'status' => 'scheduled',
                    'notes' => 'Reversed: ' . $reason,
                    'ua' => now_utc(),
                    'id' => (int) $record['payout_schedule_id'],
                    'sid' => $stokvelId,
                ]
            );
        }
    }

    public function getCycle(int $stokvelId, int $cycleId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM payout_cycles WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $cycleId, 'stokvel_id' => $stokvelId]
        );
    }

    private function calculateDistributionPool(int $stokvelId, array $cycle): string
    {
        $params = ['stokvel_id' => $stokvelId, 'posted' => LedgerService::STATUS_POSTED];
        $sql = 'SELECT COALESCE(SUM(jl.credit - jl.debit), 0) AS pool
                FROM journal_lines jl
                INNER JOIN journal_entries je ON je.id = jl.journal_entry_id AND je.stokvel_id = jl.stokvel_id
                INNER JOIN chart_of_accounts coa ON coa.id = jl.chart_of_account_id AND coa.stokvel_id = jl.stokvel_id
                WHERE jl.stokvel_id = :stokvel_id AND je.status = :posted
                  AND (coa.sub_type = :sub_type OR coa.code = :fund_code)';
        $params['sub_type'] = 'fund_balance';
        $params['fund_code'] = '3000';

        $start = $cycle['cycle_start_date'] ?? $cycle['start_date'] ?? null;
        $end = $cycle['cycle_end_date'] ?? $cycle['end_date'] ?? null;
        if (!empty($start)) {
            $sql .= ' AND je.entry_date >= :cycle_start';
            $params['cycle_start'] = $start;
        }
        if (!empty($end)) {
            $sql .= ' AND je.entry_date <= :cycle_end';
            $params['cycle_end'] = $end;
        }

        $row = $this->db->fetch($sql, $params);

        return MoneyMath::normalize($row['pool'] ?? '0');
    }

    private function recordRotationAction(
        int $stokvelId,
        int $cycleId,
        int $memberId,
        string $status,
        int $actionBy,
        string $reason,
        ?string $postponedTo = null
    ): void {
        $order = $this->db->fetch(
            'SELECT id FROM payout_schedule
             WHERE stokvel_id = :stokvel_id AND payout_cycle_id = :cycle_id AND membership_id = :member_id',
            ['stokvel_id' => $stokvelId, 'cycle_id' => $cycleId, 'member_id' => $memberId]
        );
        if ($order === null) {
            throw new FinanceException('Member is not in rotation schedule.');
        }

        $params = [
            'status' => $status === self::STATUS_POSTPONED ? 'postponed' : 'skipped',
            'notes' => $reason,
            'ua' => now_utc(),
            'id' => $order['id'],
            'sid' => $stokvelId,
        ];
        $sql = 'UPDATE payout_schedule SET status = :status, notes = :notes, updated_at = :ua';
        if ($postponedTo !== null) {
            $sql .= ', scheduled_date = :scheduled_date';
            $params['scheduled_date'] = $postponedTo;
        }
        $sql .= ' WHERE id = :id AND stokvel_id = :sid';
        $this->db->exec($sql, $params);
    }

    private function makerCheckerEnabled(int $stokvelId): bool
    {
        $row = $this->db->fetch(
            'SELECT require_dual_approval FROM stokvel_settings WHERE stokvel_id = :stokvel_id LIMIT 1',
            ['stokvel_id' => $stokvelId]
        );

        return $row !== null && (int) ($row['require_dual_approval'] ?? 0) === 1;
    }
}
