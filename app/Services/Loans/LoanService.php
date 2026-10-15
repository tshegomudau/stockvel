<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Services\Finance\ChartOfAccountsService;
use App\Services\Finance\LedgerService;
use App\Support\MoneyMath;

final class LoanService
{
    public const REGULATORY_WARNING = 'Stovel loan features are administrative tools for member-based savings groups. '
        . 'They do not constitute regulated credit provision under the National Credit Act unless your organisation '
        . 'is appropriately registered and compliant. Seek independent legal advice before offering credit to members.';

    public const STATUS_APPLIED = 'applied';
    public const STATUS_COMMITTEE_APPROVED = 'committee_approved';
    public const STATUS_COMMITTEE_REJECTED = 'committee_rejected';
    public const STATUS_DISBURSED = 'disbursed';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SETTLED = 'settled';
    public const STATUS_WRITTEN_OFF = 'written_off';
    public const STATUS_RESTRUCTURED = 'restructured';

    public function __construct(
        private readonly Database $db,
        private readonly LoanProductService $products,
        private readonly LoanScheduleCalculator $calculator,
        private readonly LedgerService $ledger,
        private readonly ChartOfAccountsService $chartOfAccounts
    ) {
    }

    public function apply(
        int $stokvelId,
        int $memberId,
        int $loanProductId,
        string $requestedAmount,
        int $termMonths,
        int $appliedBy,
        string $purpose = '',
        ?array $guarantorMemberIds = null
    ): int {
        $product = $this->products->get($stokvelId, $loanProductId);
        if ($product === null || (int) $product['is_active'] !== 1) {
            throw new FinanceException('Loan product not found or inactive.');
        }

        $amount = MoneyMath::normalize($requestedAmount);
        if (MoneyMath::comp($amount, (string) $product['min_amount']) < 0 || MoneyMath::comp($amount, (string) $product['max_amount']) > 0) {
            throw new FinanceException('Requested amount is outside product limits.');
        }
        if ($termMonths < (int) $product['min_term_months'] || $termMonths > (int) $product['max_term_months']) {
            throw new FinanceException('Requested term is outside product limits.');
        }

        $member = $this->db->fetch(
            'SELECT id FROM members WHERE id = :id AND stokvel_id = :stokvel_id AND status = :status',
            ['id' => $memberId, 'stokvel_id' => $stokvelId, 'status' => 'active']
        );
        if ($member === null) {
            throw new FinanceException('Member not found or inactive.');
        }

        if ((int) $product['requires_guarantors'] === 1) {
            $guarantorMemberIds = $guarantorMemberIds ?? [];
            if (count($guarantorMemberIds) < (int) $product['min_guarantors']) {
                throw new FinanceException('Insufficient guarantors for this loan product.');
            }
        }

        $now = now_utc();
        $loanId = (int) $this->db->insert(
            'INSERT INTO loans
                (stokvel_id, member_id, loan_product_id, requested_amount, approved_amount, term_months,
                 annual_interest_rate_percent, amortization_method, instalment_fee, penalty_rate_percent,
                 status, purpose, applied_by, applied_at, outstanding_principal, outstanding_interest,
                 outstanding_fees, outstanding_penalties, in_arrears, created_at, updated_at)
             VALUES
                (:stokvel_id, :member_id, :loan_product_id, :requested_amount, NULL, :term_months,
                 :annual_interest_rate_percent, :amortization_method, :instalment_fee, :penalty_rate_percent,
                 :status, :purpose, :applied_by, :applied_at, :outstanding_principal, :outstanding_interest,
                 :outstanding_fees, :outstanding_penalties, 0, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'member_id' => $memberId,
                'loan_product_id' => $loanProductId,
                'requested_amount' => $amount,
                'term_months' => $termMonths,
                'annual_interest_rate_percent' => (string) $product['annual_interest_rate_percent'],
                'amortization_method' => (string) $product['amortization_method'],
                'instalment_fee' => (string) $product['instalment_fee'],
                'penalty_rate_percent' => (string) $product['penalty_rate_percent'],
                'status' => self::STATUS_APPLIED,
                'purpose' => $purpose,
                'applied_by' => $appliedBy,
                'applied_at' => $now,
                'outstanding_principal' => '0.00',
                'outstanding_interest' => '0.00',
                'outstanding_fees' => '0.00',
                'outstanding_penalties' => '0.00',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        foreach ($guarantorMemberIds ?? [] as $guarantorId) {
            $this->db->insert(
                'INSERT INTO loan_guarantors (stokvel_id, loan_id, member_id, created_at)
                 VALUES (:stokvel_id, :loan_id, :member_id, :created_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'loan_id' => $loanId,
                    'member_id' => (int) $guarantorId,
                    'created_at' => $now,
                ]
            );
        }

        return $loanId;
    }

    public function committeeApprove(
        int $stokvelId,
        int $loanId,
        int $committeeMemberId,
        ?string $approvedAmount = null,
        ?string $committeeNotes = null
    ): void {
        $loan = $this->lockLoan($stokvelId, $loanId);
        if ($loan['status'] !== self::STATUS_APPLIED) {
            throw new FinanceException('Only applied loans can be committee-approved.');
        }

        $amount = MoneyMath::normalize($approvedAmount ?? (string) $loan['requested_amount']);
        $this->db->exec(
            'UPDATE loans SET status = :status, approved_amount = :approved_amount, committee_approved_by = :approved_by,
             committee_approved_at = :approved_at, committee_notes = :notes, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => self::STATUS_COMMITTEE_APPROVED,
                'approved_amount' => $amount,
                'approved_by' => $committeeMemberId,
                'approved_at' => now_utc(),
                'notes' => $committeeNotes,
                'updated_at' => now_utc(),
                'id' => $loanId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    public function committeeReject(int $stokvelId, int $loanId, int $committeeMemberId, string $reason): void
    {
        $loan = $this->lockLoan($stokvelId, $loanId);
        if ($loan['status'] !== self::STATUS_APPLIED) {
            throw new FinanceException('Only applied loans can be committee-rejected.');
        }

        $this->db->exec(
            'UPDATE loans SET status = :status, committee_rejected_by = :rejected_by, committee_rejected_at = :rejected_at,
             committee_rejection_reason = :reason, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => self::STATUS_COMMITTEE_REJECTED,
                'rejected_by' => $committeeMemberId,
                'rejected_at' => now_utc(),
                'reason' => $reason,
                'updated_at' => now_utc(),
                'id' => $loanId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    public function disburse(int $stokvelId, int $loanId, int $disbursedBy, ?string $disbursementDate = null): void
    {
        $this->db->transaction(function () use ($stokvelId, $loanId, $disbursedBy, $disbursementDate): void {
            $loan = $this->lockLoan($stokvelId, $loanId);
            if ($loan['status'] !== self::STATUS_COMMITTEE_APPROVED) {
                throw new FinanceException('Loan must be committee-approved before disbursement.');
            }

            $amount = MoneyMath::normalize((string) $loan['approved_amount']);
            $disbursementDate = $disbursementDate ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');

            $schedule = $this->calculator->buildSchedule(
                $amount,
                (string) $loan['annual_interest_rate_percent'],
                (int) $loan['term_months'],
                (string) $loan['amortization_method'],
                (new \DateTimeImmutable($disbursementDate))->modify('+1 month')->format('Y-m-d'),
                (string) $loan['instalment_fee']
            );
            $this->persistSchedule($stokvelId, $loanId, $schedule);

            $bank = $this->chartOfAccounts->requireBySubType($stokvelId, 'bank');
            $loanReceivable = $this->chartOfAccounts->requireBySubType($stokvelId, 'loan_receivable');

            $journalId = $this->ledger->createDraft(
                $stokvelId,
                $disbursedBy,
                $disbursementDate,
                'LOAN-DISB-' . $loanId,
                'Loan disbursement #' . $loanId
            );
            $this->ledger->addLine(
                $stokvelId,
                $journalId,
                (int) $loanReceivable['id'],
                $amount,
                '0.00',
                (int) $loan['member_id'],
                'Loan principal disbursed'
            );
            $this->ledger->addLine(
                $stokvelId,
                $journalId,
                (int) $bank['id'],
                '0.00',
                $amount,
                null,
                'Bank outflow'
            );
            $this->ledger->post($stokvelId, $journalId, $disbursedBy);

            $this->db->exec(
                'UPDATE loans SET status = :status, disbursed_amount = :amount, disbursed_by = :disbursed_by,
                 disbursed_at = :disbursed_at, disbursement_journal_entry_id = :journal_id,
                 outstanding_principal = :outstanding_principal, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_ACTIVE,
                    'amount' => $amount,
                    'disbursed_by' => $disbursedBy,
                    'disbursed_at' => now_utc(),
                    'journal_id' => $journalId,
                    'outstanding_principal' => $amount,
                    'updated_at' => now_utc(),
                    'id' => $loanId,
                    'stokvel_id' => $stokvelId,
                ]
            );
        });
    }

    public function repay(int $stokvelId, int $loanId, string $amount, int $recordedBy, ?string $paidAt = null): array
    {
        return $this->db->transaction(function () use ($stokvelId, $loanId, $amount, $recordedBy, $paidAt): array {
            $loan = $this->lockLoan($stokvelId, $loanId);
            if (!in_array($loan['status'], [self::STATUS_ACTIVE, self::STATUS_DISBURSED], true)) {
                throw new FinanceException('Loan is not active.');
            }

            $amount = MoneyMath::normalize($amount);
            if (!MoneyMath::isPositive($amount)) {
                throw new FinanceException('Repayment amount must be positive.');
            }

            $allocation = $this->allocateRepayment($stokvelId, $loanId, $amount);
            $paidAt = $paidAt ?? now_utc();

            $bank = $this->chartOfAccounts->requireBySubType($stokvelId, 'bank');
            $loanReceivable = $this->chartOfAccounts->requireBySubType($stokvelId, 'loan_receivable');
            $interestIncome = $this->chartOfAccounts->requireBySubType($stokvelId, 'interest');
            $penaltyIncome = $this->chartOfAccounts->requireBySubType($stokvelId, 'penalties');
            $otherIncome = $this->chartOfAccounts->requireBySubType($stokvelId, 'other_income');

            $journalId = $this->ledger->createDraft(
                $stokvelId,
                $recordedBy,
                (new \DateTimeImmutable($paidAt))->format('Y-m-d'),
                'LOAN-REP-' . $loanId . '-' . time(),
                'Loan repayment #' . $loanId
            );
            $this->ledger->addLine($stokvelId, $journalId, (int) $bank['id'], $amount, '0.00', null, 'Repayment received');
            if (MoneyMath::isPositive($allocation['principal'])) {
                $this->ledger->addLine(
                    $stokvelId,
                    $journalId,
                    (int) $loanReceivable['id'],
                    '0.00',
                    $allocation['principal'],
                    (int) $loan['member_id'],
                    'Principal repayment'
                );
            }
            if (MoneyMath::isPositive($allocation['interest'])) {
                $this->ledger->addLine(
                    $stokvelId,
                    $journalId,
                    (int) $interestIncome['id'],
                    '0.00',
                    $allocation['interest'],
                    (int) $loan['member_id'],
                    'Interest repayment'
                );
            }
            if (MoneyMath::isPositive($allocation['penalty'])) {
                $this->ledger->addLine(
                    $stokvelId,
                    $journalId,
                    (int) $penaltyIncome['id'],
                    '0.00',
                    $allocation['penalty'],
                    (int) $loan['member_id'],
                    'Penalty repayment'
                );
            }
            if (MoneyMath::isPositive($allocation['fee'])) {
                $this->ledger->addLine(
                    $stokvelId,
                    $journalId,
                    (int) $otherIncome['id'],
                    '0.00',
                    $allocation['fee'],
                    (int) $loan['member_id'],
                    'Fee repayment'
                );
            }

            $this->ledger->post($stokvelId, $journalId, $recordedBy);

            $newPrincipal = MoneyMath::sub((string) $loan['outstanding_principal'], $allocation['principal']);
            $newInterest = MoneyMath::sub((string) $loan['outstanding_interest'], $allocation['interest']);
            $newFees = MoneyMath::sub((string) $loan['outstanding_fees'], $allocation['fee']);
            $newPenalties = MoneyMath::sub((string) $loan['outstanding_penalties'], $allocation['penalty']);

            $status = self::STATUS_ACTIVE;
            if (
                MoneyMath::isZero($newPrincipal)
                && MoneyMath::isZero($newInterest)
                && MoneyMath::isZero($newFees)
                && MoneyMath::isZero($newPenalties)
            ) {
                $status = self::STATUS_SETTLED;
            }

            $this->db->exec(
                'UPDATE loans SET outstanding_principal = :principal, outstanding_interest = :interest,
                 outstanding_fees = :fees, outstanding_penalties = :penalties, status = :status,
                 in_arrears = :in_arrears, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'principal' => MoneyMath::max($newPrincipal, '0.00'),
                    'interest' => MoneyMath::max($newInterest, '0.00'),
                    'fees' => MoneyMath::max($newFees, '0.00'),
                    'penalties' => MoneyMath::max($newPenalties, '0.00'),
                    'status' => $status,
                    'in_arrears' => 0,
                    'updated_at' => now_utc(),
                    'id' => $loanId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            $this->db->insert(
                'INSERT INTO loan_repayments
                    (stokvel_id, loan_id, amount, principal_component, interest_component, fee_component, penalty_component,
                     journal_entry_id, paid_at, recorded_by, created_at)
                 VALUES
                    (:stokvel_id, :loan_id, :amount, :principal, :interest, :fee, :penalty,
                     :journal_id, :paid_at, :recorded_by, :created_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'loan_id' => $loanId,
                    'amount' => $amount,
                    'principal' => $allocation['principal'],
                    'interest' => $allocation['interest'],
                    'fee' => $allocation['fee'],
                    'penalty' => $allocation['penalty'],
                    'journal_id' => $journalId,
                    'paid_at' => $paidAt,
                    'recorded_by' => $recordedBy,
                    'created_at' => now_utc(),
                ]
            );

            return $allocation;
        });
    }

    public function earlySettle(int $stokvelId, int $loanId, int $recordedBy): array
    {
        $loan = $this->getLoan($stokvelId, $loanId);
        if ($loan === null) {
            throw new FinanceException('Loan not found.');
        }

        $schedule = $this->getSchedule($stokvelId, $loanId);
        $nextInstalment = null;
        foreach ($schedule as $row) {
            if (($row['status'] ?? 'pending') !== 'paid') {
                $nextInstalment = (int) $row['instalment_number'];
                break;
            }
        }
        if ($nextInstalment === null) {
            throw new FinanceException('Loan has no outstanding instalments.');
        }

        $settlement = $this->calculator->calculateEarlySettlement(
            array_map(static fn (array $row): array => [
                'instalment_number' => (int) $row['instalment_number'],
                'due_date' => (string) $row['due_date'],
                'opening_balance' => (string) $row['opening_balance'],
                'principal' => (string) $row['principal_amount'],
                'interest' => (string) $row['interest_amount'],
                'fee' => (string) $row['fee_amount'],
                'total_instalment' => (string) $row['total_instalment'],
                'closing_balance' => (string) $row['closing_balance'],
            ], $schedule),
            $nextInstalment,
            (string) $loan['penalty_rate_percent']
        );

        $this->repay($stokvelId, $loanId, $settlement['settlement_total'], $recordedBy);

        return $settlement;
    }

    public function flagArrears(int $stokvelId, ?string $asOfDate = null): int
    {
        $asOfDate = $asOfDate ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $overdueLoans = $this->db->fetchAll(
            'SELECT DISTINCT l.id
             FROM loans l
             INNER JOIN loan_schedule_items ls ON ls.loan_id = l.id AND ls.stokvel_id = l.stokvel_id
             WHERE l.stokvel_id = :stokvel_id AND l.status = :active
               AND ls.status IN (\'pending\', \'partial\', \'overdue\') AND ls.due_date < :as_of_date',
            [
                'stokvel_id' => $stokvelId,
                'active' => self::STATUS_ACTIVE,
                'as_of_date' => $asOfDate,
            ]
        );

        $count = 0;
        foreach ($overdueLoans as $row) {
            $this->db->exec(
                'UPDATE loans SET in_arrears = 1, updated_at = :updated_at WHERE id = :id AND stokvel_id = :stokvel_id',
                ['updated_at' => now_utc(), 'id' => $row['id'], 'stokvel_id' => $stokvelId]
            );
            ++$count;
        }

        return $count;
    }

    public function restructure(
        int $stokvelId,
        int $loanId,
        int $newTermMonths,
        ?string $newRatePercent,
        int $restructuredBy,
        string $reason
    ): int {
        return (int) $this->db->transaction(function () use ($stokvelId, $loanId, $newTermMonths, $newRatePercent, $restructuredBy, $reason) {
            $loan = $this->lockLoan($stokvelId, $loanId);
            if ($loan['status'] !== self::STATUS_ACTIVE) {
                throw new FinanceException('Only active loans can be restructured.');
            }

            $outstanding = MoneyMath::normalize((string) $loan['outstanding_principal']);
            $rate = MoneyMath::normalize($newRatePercent ?? (string) $loan['annual_interest_rate_percent']);
            $startDate = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+1 month')->format('Y-m-d');

            $this->db->exec(
                'UPDATE loans SET status = :status, updated_at = :updated_at WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_RESTRUCTURED,
                    'updated_at' => now_utc(),
                    'id' => $loanId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            $newLoanId = $this->apply(
                $stokvelId,
                (int) $loan['member_id'],
                (int) $loan['loan_product_id'],
                $outstanding,
                $newTermMonths,
                $restructuredBy,
                'Restructured from loan #' . $loanId . ': ' . $reason
            );

            $this->db->exec(
                'UPDATE loans SET status = :status, approved_amount = :amount, annual_interest_rate_percent = :rate,
                 parent_loan_id = :parent_loan_id, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_COMMITTEE_APPROVED,
                    'amount' => $outstanding,
                    'rate' => $rate,
                    'parent_loan_id' => $loanId,
                    'updated_at' => now_utc(),
                    'id' => $newLoanId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            $this->db->insert(
                'INSERT INTO loan_restructure_audit
                    (stokvel_id, original_loan_id, new_loan_id, reason, restructured_by, created_at)
                 VALUES
                    (:stokvel_id, :original_loan_id, :new_loan_id, :reason, :restructured_by, :created_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'original_loan_id' => $loanId,
                    'new_loan_id' => $newLoanId,
                    'reason' => $reason,
                    'restructured_by' => $restructuredBy,
                    'created_at' => now_utc(),
                ]
            );

            $schedule = $this->calculator->buildSchedule(
                $outstanding,
                $rate,
                $newTermMonths,
                (string) $loan['amortization_method'],
                $startDate,
                (string) $loan['instalment_fee']
            );
            $this->persistSchedule($stokvelId, $newLoanId, $schedule);

            $this->db->exec(
                'UPDATE loans SET status = :status, disbursed_amount = :amount, disbursed_by = :disbursed_by,
                 disbursed_at = :disbursed_at, outstanding_principal = :outstanding_principal, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_ACTIVE,
                    'amount' => $outstanding,
                    'disbursed_by' => $restructuredBy,
                    'disbursed_at' => now_utc(),
                    'outstanding_principal' => $outstanding,
                    'updated_at' => now_utc(),
                    'id' => $newLoanId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            return $newLoanId;
        });
    }

    public function writeOff(int $stokvelId, int $loanId, int $writtenOffBy, string $reason): void
    {
        $this->db->transaction(function () use ($stokvelId, $loanId, $writtenOffBy, $reason): void {
            $loan = $this->lockLoan($stokvelId, $loanId);
            if (!in_array($loan['status'], [self::STATUS_ACTIVE, self::STATUS_DISBURSED], true)) {
                throw new FinanceException('Loan cannot be written off in its current status.');
            }

            $totalOutstanding = MoneyMath::sum([
                (string) $loan['outstanding_principal'],
                (string) $loan['outstanding_interest'],
                (string) $loan['outstanding_fees'],
                (string) $loan['outstanding_penalties'],
            ]);
            if (!MoneyMath::isPositive($totalOutstanding)) {
                throw new FinanceException('Loan has no outstanding balance to write off.');
            }

            $loanReceivable = $this->chartOfAccounts->requireBySubType($stokvelId, 'loan_receivable');
            $loanLoss = $this->chartOfAccounts->requireBySubType($stokvelId, 'loan_loss');

            $journalId = $this->ledger->createDraft(
                $stokvelId,
                $writtenOffBy,
                (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d'),
                'LOAN-WO-' . $loanId,
                'Loan write-off: ' . $reason
            );
            $this->ledger->addLine(
                $stokvelId,
                $journalId,
                (int) $loanLoss['id'],
                $totalOutstanding,
                '0.00',
                null,
                'Loan loss expense'
            );
            $this->ledger->addLine(
                $stokvelId,
                $journalId,
                (int) $loanReceivable['id'],
                '0.00',
                $totalOutstanding,
                (int) $loan['member_id'],
                'Loan receivable written off'
            );
            $this->ledger->post($stokvelId, $journalId, $writtenOffBy);

            $this->db->exec(
                'UPDATE loans SET status = :status, written_off_by = :written_off_by, written_off_at = :written_off_at,
                 write_off_reason = :reason, write_off_journal_entry_id = :journal_id,
                 outstanding_principal = :zero, outstanding_interest = :zero2, outstanding_fees = :zero3,
                 outstanding_penalties = :zero4, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_WRITTEN_OFF,
                    'written_off_by' => $writtenOffBy,
                    'written_off_at' => now_utc(),
                    'reason' => $reason,
                    'journal_id' => $journalId,
                    'zero' => '0.00',
                    'zero2' => '0.00',
                    'zero3' => '0.00',
                    'zero4' => '0.00',
                    'updated_at' => now_utc(),
                    'id' => $loanId,
                    'stokvel_id' => $stokvelId,
                ]
            );
        });
    }

    public function getLoan(int $stokvelId, int $loanId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM loans WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $loanId, 'stokvel_id' => $stokvelId]
        );
    }

    public function getSchedule(int $stokvelId, int $loanId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM loan_schedule_items
             WHERE loan_id = :loan_id AND stokvel_id = :stokvel_id
             ORDER BY instalment_number ASC',
            ['loan_id' => $loanId, 'stokvel_id' => $stokvelId]
        );
    }

    /** @param list<array<string, mixed>> $schedule */
    private function persistSchedule(int $stokvelId, int $loanId, array $schedule): void
    {
        foreach ($schedule as $row) {
            $this->db->insert(
                'INSERT INTO loan_schedule_items
                    (stokvel_id, loan_id, instalment_number, due_date, opening_balance, principal_amount, interest_amount,
                     fee_amount, total_instalment, closing_balance, paid_amount, status, created_at, updated_at)
                 VALUES
                    (:stokvel_id, :loan_id, :instalment_number, :due_date, :opening_balance, :principal_amount, :interest_amount,
                     :fee_amount, :total_instalment, :closing_balance, :paid_amount, :status, :created_at, :updated_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'loan_id' => $loanId,
                    'instalment_number' => $row['instalment_number'],
                    'due_date' => $row['due_date'],
                    'opening_balance' => $row['opening_balance'],
                    'principal_amount' => $row['principal'],
                    'interest_amount' => $row['interest'],
                    'fee_amount' => $row['fee'],
                    'total_instalment' => $row['total_instalment'],
                    'closing_balance' => $row['closing_balance'],
                    'paid_amount' => '0.00',
                    'status' => 'pending',
                    'created_at' => now_utc(),
                    'updated_at' => now_utc(),
                ]
            );
        }
    }

    /** @return array{principal: string, interest: string, fee: string, penalty: string} */
    private function allocateRepayment(int $stokvelId, int $loanId, string $amount): array
    {
        $remaining = $amount;
        $allocation = ['principal' => '0.00', 'interest' => '0.00', 'fee' => '0.00', 'penalty' => '0.00'];

        $instalments = $this->db->fetchAll(
            'SELECT * FROM loan_schedule_items
             WHERE loan_id = :loan_id AND stokvel_id = :stokvel_id AND status IN (\'pending\', \'partial\', \'overdue\')
             ORDER BY due_date ASC, instalment_number ASC FOR UPDATE',
            [
                'loan_id' => $loanId,
                'stokvel_id' => $stokvelId,
            ]
        );

        foreach ($instalments as $instalment) {
            if (!MoneyMath::isPositive($remaining)) {
                break;
            }

            $instalmentDue = MoneyMath::sub((string) $instalment['total_instalment'], (string) $instalment['paid_amount']);
            if (!MoneyMath::isPositive($instalmentDue)) {
                continue;
            }

            $apply = MoneyMath::min($remaining, $instalmentDue);
            $remaining = MoneyMath::sub($remaining, $apply);

            $penaltyDue = (string) ($instalment['penalty_amount'] ?? '0.00');
            $feeDue = (string) $instalment['fee_amount'];
            $interestDue = (string) $instalment['interest_amount'];
            $principalDue = (string) $instalment['principal_amount'];
            $componentTotal = MoneyMath::sum([$penaltyDue, $feeDue, $interestDue, $principalDue]);

            if (MoneyMath::isZero($componentTotal)) {
                $allocation['principal'] = MoneyMath::add($allocation['principal'], $apply);
            } else {
                $toPenalty = MoneyMath::div(MoneyMath::mul($apply, $penaltyDue), $componentTotal);
                $toFee = MoneyMath::div(MoneyMath::mul($apply, $feeDue), $componentTotal);
                $toInterest = MoneyMath::div(MoneyMath::mul($apply, $interestDue), $componentTotal);
                $toPrincipal = MoneyMath::sub(MoneyMath::sub(MoneyMath::sub($apply, $toPenalty), $toFee), $toInterest);

                $allocation['penalty'] = MoneyMath::add($allocation['penalty'], $toPenalty);
                $allocation['fee'] = MoneyMath::add($allocation['fee'], $toFee);
                $allocation['interest'] = MoneyMath::add($allocation['interest'], $toInterest);
                $allocation['principal'] = MoneyMath::add($allocation['principal'], $toPrincipal);
            }

            $newPaid = MoneyMath::add((string) $instalment['paid_amount'], $apply);
            $status = MoneyMath::comp($newPaid, (string) $instalment['total_instalment']) >= 0 ? 'paid' : 'partial';
            $this->db->exec(
                'UPDATE loan_schedule_items SET paid_amount = :paid_amount, status = :status, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'paid_amount' => $newPaid,
                    'status' => $status,
                    'updated_at' => now_utc(),
                    'id' => $instalment['id'],
                    'stokvel_id' => $stokvelId,
                ]
            );
        }

        if (MoneyMath::isPositive($remaining)) {
            $allocation['principal'] = MoneyMath::add($allocation['principal'], $remaining);
        }

        return $allocation;
    }

    private function lockLoan(int $stokvelId, int $loanId): array
    {
        $loan = $this->db->fetch(
            'SELECT * FROM loans WHERE id = :id AND stokvel_id = :stokvel_id FOR UPDATE',
            ['id' => $loanId, 'stokvel_id' => $stokvelId]
        );
        if ($loan === null) {
            throw new FinanceException('Loan not found.');
        }

        return $loan;
    }
}
