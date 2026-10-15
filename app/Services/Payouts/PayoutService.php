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
        return (int) $this->db->insert(
            'INSERT INTO payout_cycles
                (stokvel_id, name, cycle_start_date, cycle_end_date, status, created_by, created_at, updated_at)
             VALUES
                (:stokvel_id, :name, :cycle_start_date, :cycle_end_date, :status, :created_by, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'name' => $name,
                'cycle_start_date' => $cycleStartDate,
                'cycle_end_date' => $cycleEndDate,
                'status' => self::STATUS_PLANNED,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /** @param list<int> $memberIdsInOrder */
    public function setBeneficiaryOrder(int $stokvelId, int $cycleId, array $memberIdsInOrder): void
    {
        $cycle = $this->getCycle($stokvelId, $cycleId);
        if ($cycle === null) {
            throw new FinanceException('Payout cycle not found.');
        }

        $this->db->transaction(function () use ($stokvelId, $cycleId, $memberIdsInOrder): void {
            $this->db->exec(
                'DELETE FROM payout_rotation_order WHERE stokvel_id = :stokvel_id AND payout_cycle_id = :cycle_id',
                ['stokvel_id' => $stokvelId, 'cycle_id' => $cycleId]
            );

            $position = 1;
            foreach ($memberIdsInOrder as $memberId) {
                $member = $this->db->fetch(
                    'SELECT id FROM members WHERE id = :id AND stokvel_id = :stokvel_id',
                    ['id' => $memberId, 'stokvel_id' => $stokvelId]
                );
                if ($member === null) {
                    throw new FinanceException('Invalid member in rotation order.');
                }

                $this->db->insert(
                    'INSERT INTO payout_rotation_order
                        (stokvel_id, payout_cycle_id, member_id, position, created_at, updated_at)
                     VALUES
                        (:stokvel_id, :payout_cycle_id, :member_id, :position, :created_at, :updated_at)',
                    [
                        'stokvel_id' => $stokvelId,
                        'payout_cycle_id' => $cycleId,
                        'member_id' => $memberId,
                        'position' => $position,
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
        if ((int) ($cycle['created_by'] ?? 0) === $approvedBy && $this->makerCheckerEnabled($stokvelId)) {
            throw new AuthorizationException('Maker-checker: cycle creator cannot approve the payout cycle.');
        }

        $this->db->exec(
            'UPDATE payout_cycles SET status = :status, approved_by = :approved_by, approved_at = :approved_at, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => self::STATUS_APPROVED,
                'approved_by' => $approvedBy,
                'approved_at' => now_utc(),
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
            if ($cycle === null || !in_array($cycle['status'], [self::STATUS_APPROVED, self::STATUS_PLANNED], true)) {
                throw new FinanceException('Payout cycle must be approved before recording payouts.');
            }

            $eligibility = $this->checkEligibility($stokvelId, $cycleId, $memberId);
            if (!$eligibility['eligible']) {
                throw new FinanceException('Member is not eligible: ' . implode(' ', $eligibility['reasons']));
            }

            $amount = MoneyMath::normalize($amount);
            $payoutDate = $payoutDate ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');

            $payoutId = (int) $this->db->insert(
                'INSERT INTO payout_records
                    (stokvel_id, payout_cycle_id, member_id, amount, status, payout_date, recorded_by, created_at, updated_at)
                 VALUES
                    (:stokvel_id, :payout_cycle_id, :member_id, :amount, :status, :payout_date, :recorded_by, :created_at, :updated_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'payout_cycle_id' => $cycleId,
                    'member_id' => $memberId,
                    'amount' => $amount,
                    'status' => self::STATUS_PAID,
                    'payout_date' => $payoutDate,
                    'recorded_by' => $recordedBy,
                    'created_at' => now_utc(),
                    'updated_at' => now_utc(),
                ]
            );

            $bank = $this->chartOfAccounts->requireBySubType($stokvelId, 'bank');
            $payoutExpense = $this->chartOfAccounts->requireBySubType($stokvelId, 'payout');

            $journalId = $this->ledger->createDraft(
                $stokvelId,
                $recordedBy,
                $payoutDate,
                'PAYOUT-' . $payoutId,
                'Rotational payout #' . $payoutId
            );
            $this->ledger->addLine($stokvelId, $journalId, (int) $payoutExpense['id'], $amount, '0.00', null, 'Payout expense');
            $this->ledger->addLine($stokvelId, $journalId, (int) $bank['id'], '0.00', $amount, null, 'Bank outflow');
            $this->ledger->post($stokvelId, $journalId, $recordedBy);

            $this->db->exec(
                'UPDATE payout_records SET journal_entry_id = :journal_id, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'journal_id' => $journalId,
                    'updated_at' => now_utc(),
                    'id' => $payoutId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            return $payoutId;
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
        $this->db->transaction(function () use ($stokvelId, $cycleId, $memberAId, $memberBId, $actionBy, $reason): void {
            $a = $this->db->fetch(
                'SELECT * FROM payout_rotation_order
                 WHERE stokvel_id = :stokvel_id AND payout_cycle_id = :cycle_id AND member_id = :member_id FOR UPDATE',
                ['stokvel_id' => $stokvelId, 'cycle_id' => $cycleId, 'member_id' => $memberAId]
            );
            $b = $this->db->fetch(
                'SELECT * FROM payout_rotation_order
                 WHERE stokvel_id = :stokvel_id AND payout_cycle_id = :cycle_id AND member_id = :member_id FOR UPDATE',
                ['stokvel_id' => $stokvelId, 'cycle_id' => $cycleId, 'member_id' => $memberBId]
            );
            if ($a === null || $b === null) {
                throw new FinanceException('Both members must exist in the rotation order.');
            }

            $this->db->exec(
                'UPDATE payout_rotation_order SET position = :position, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                ['position' => (int) $b['position'], 'updated_at' => now_utc(), 'id' => $a['id'], 'stokvel_id' => $stokvelId]
            );
            $this->db->exec(
                'UPDATE payout_rotation_order SET position = :position, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                ['position' => (int) $a['position'], 'updated_at' => now_utc(), 'id' => $b['id'], 'stokvel_id' => $stokvelId]
            );

            $this->db->insert(
                'INSERT INTO payout_rotation_audit
                    (stokvel_id, payout_cycle_id, action, member_a_id, member_b_id, reason, action_by, created_at)
                 VALUES
                    (:stokvel_id, :cycle_id, :action, :member_a_id, :member_b_id, :reason, :action_by, :created_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'cycle_id' => $cycleId,
                    'action' => 'swap',
                    'member_a_id' => $memberAId,
                    'member_b_id' => $memberBId,
                    'reason' => $reason,
                    'action_by' => $actionBy,
                    'created_at' => now_utc(),
                ]
            );
        });
    }

    public function reversePayout(int $stokvelId, int $payoutRecordId, int $reversedBy, string $reason): void
    {
        $record = $this->db->fetch(
            'SELECT * FROM payout_records WHERE id = :id AND stokvel_id = :stokvel_id',
            ['id' => $payoutRecordId, 'stokvel_id' => $stokvelId]
        );
        if ($record === null || $record['status'] !== self::STATUS_PAID) {
            throw new FinanceException('Paid payout record not found.');
        }
        if ($record['journal_entry_id'] === null) {
            throw new FinanceException('Payout has no linked journal entry.');
        }

        $reversalJournalId = $this->ledger->reverse($stokvelId, (int) $record['journal_entry_id'], $reversedBy, $reason);

        $this->db->exec(
            'UPDATE payout_records SET status = :status, reversed_by = :reversed_by, reversed_at = :reversed_at,
             reversal_journal_entry_id = :journal_id, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => self::STATUS_REVERSED,
                'reversed_by' => $reversedBy,
                'reversed_at' => now_utc(),
                'journal_id' => $reversalJournalId,
                'updated_at' => now_utc(),
                'id' => $payoutRecordId,
                'stokvel_id' => $stokvelId,
            ]
        );
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
            'SELECT id FROM payout_rotation_order
             WHERE stokvel_id = :stokvel_id AND payout_cycle_id = :cycle_id AND member_id = :member_id',
            ['stokvel_id' => $stokvelId, 'cycle_id' => $cycleId, 'member_id' => $memberId]
        );
        if ($order === null) {
            throw new FinanceException('Member is not in rotation order.');
        }

        $this->db->insert(
            'INSERT INTO payout_rotation_events
                (stokvel_id, payout_cycle_id, member_id, event_status, reason, postponed_to, action_by, created_at)
             VALUES
                (:stokvel_id, :cycle_id, :member_id, :event_status, :reason, :postponed_to, :action_by, :created_at)',
            [
                'stokvel_id' => $stokvelId,
                'cycle_id' => $cycleId,
                'member_id' => $memberId,
                'event_status' => $status,
                'reason' => $reason,
                'postponed_to' => $postponedTo,
                'action_by' => $actionBy,
                'created_at' => now_utc(),
            ]
        );

        $this->db->insert(
            'INSERT INTO payout_rotation_audit
                (stokvel_id, payout_cycle_id, action, member_a_id, member_b_id, reason, action_by, created_at)
             VALUES
                (:stokvel_id, :cycle_id, :action, :member_a_id, NULL, :reason, :action_by, :created_at)',
            [
                'stokvel_id' => $stokvelId,
                'cycle_id' => $cycleId,
                'action' => $status,
                'member_a_id' => $memberId,
                'reason' => $reason,
                'action_by' => $actionBy,
                'created_at' => now_utc(),
            ]
        );
    }

    private function makerCheckerEnabled(int $stokvelId): bool
    {
        $row = $this->db->fetch(
            'SELECT payout_maker_checker FROM stokvel_settings WHERE stokvel_id = :stokvel_id LIMIT 1',
            ['stokvel_id' => $stokvelId]
        );

        return $row === null || (int) ($row['payout_maker_checker'] ?? 1) === 1;
    }
}
