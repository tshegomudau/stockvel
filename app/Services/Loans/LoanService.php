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
    public const REGULATORY_WARNING = 'stockvel loan features are administrative tools for member-based savings groups. '
        . 'They do not constitute regulated credit provision under the National Credit Act unless your organisation '
        . 'is appropriately registered and compliant. Seek independent legal advice before offering credit to members.';

    public const STATUS_APPLIED = 'submitted';
    public const STATUS_COMMITTEE_APPROVED = 'approved';
    public const STATUS_COMMITTEE_REJECTED = 'rejected';
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
        if ($product === null || (int) ($product['is_active'] ?? 0) !== 1) {
            throw new FinanceException('Loan product not found or inactive.');
        }

        $amount = MoneyMath::normalize($requestedAmount);
        if (MoneyMath::comp($amount, (string) $product['min_amount']) < 0 || MoneyMath::comp($amount, (string) $product['max_amount']) > 0) {
            throw new FinanceException('Requested amount is outside product limits.');
        }

        $maxTerm = (int) ($product['max_term_months'] ?? $product['min_term_months'] ?? 0);
        if ($termMonths < 1 || ($maxTerm > 0 && $termMonths > $maxTerm)) {
            throw new FinanceException('Requested term is outside product limits.');
        }

        $member = $this->db->fetch(
            'SELECT id FROM memberships WHERE id = :id AND stokvel_id = :stokvel_id AND status = :status',
            ['id' => $memberId, 'stokvel_id' => $stokvelId, 'status' => 'active']
        );
        if ($member === null) {
            throw new FinanceException('Member not found or inactive.');
        }

        $requiresGuarantors = (int) ($product['requires_guarantors'] ?? $product['requires_guarantor'] ?? 0);
        $minGuarantors = (int) ($product['min_guarantors'] ?? ($requiresGuarantors ? 1 : 0));
        if ($requiresGuarantors === 1) {
            $guarantorMemberIds = $guarantorMemberIds ?? [];
            if (count($guarantorMemberIds) < $minGuarantors) {
                throw new FinanceException('Insufficient guarantors for this loan product.');
            }
        }

        $now = now_utc();
        $applicationId = (int) $this->db->insert(
            'INSERT INTO loan_applications
                (stokvel_id, membership_id, loan_product_id, application_ref, requested_amount, term_months,
                 purpose, status, submitted_at, created_at, updated_at)
             VALUES
                (:stokvel_id, :membership_id, :loan_product_id, :application_ref, :requested_amount, :term_months,
                 :purpose, :status, :submitted_at, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'membership_id' => $memberId,
                'loan_product_id' => $loanProductId,
                'application_ref' => $this->nextApplicationRef($stokvelId),
                'requested_amount' => $amount,
                'term_months' => $termMonths,
                'purpose' => $purpose,
                'status' => self::STATUS_APPLIED,
                'submitted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $guarantorMemberIds = $guarantorMemberIds ?? [];
        if ($guarantorMemberIds !== []) {
            $each = MoneyMath::div($amount, (string) count($guarantorMemberIds));
            foreach ($guarantorMemberIds as $guarantorId) {
                $this->db->insert(
                    'INSERT INTO loan_guarantors
                        (stokvel_id, loan_application_id, guarantor_membership_id, guaranteed_amount, status, created_at, updated_at)
                     VALUES
                        (:stokvel_id, :loan_application_id, :guarantor_membership_id, :guaranteed_amount, :status, :created_at, :updated_at)',
                    [
                        'stokvel_id' => $stokvelId,
                        'loan_application_id' => $applicationId,
                        'guarantor_membership_id' => (int) $guarantorId,
                        'guaranteed_amount' => $each,
                        'status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        return $applicationId;
    }

    public function committeeApprove(
        int $stokvelId,
        int $loanId,
        int $committeeMemberId,
        ?string $approvedAmount = null,
        ?string $committeeNotes = null
    ): void {
        $application = $this->lockApplication($stokvelId, $loanId);
        if ($application['status'] !== self::STATUS_APPLIED) {
            throw new FinanceException('Only applied loans can be committee-approved.');
        }

        $amount = MoneyMath::normalize($approvedAmount ?? (string) $application['requested_amount']);
        if (MoneyMath::comp($amount, (string) $application['requested_amount']) > 0) {
            throw new FinanceException('Approved amount cannot exceed requested amount.');
        }

        $this->db->exec(
            'UPDATE loan_applications SET status = :status, decided_at = :decided_at, decided_by_user_id = :decided_by_user_id,
             decision_notes = :decision_notes, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => self::STATUS_COMMITTEE_APPROVED,
                'decided_at' => now_utc(),
                'decided_by_user_id' => $committeeMemberId,
                'decision_notes' => $committeeNotes,
                'updated_at' => now_utc(),
                'id' => $loanId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    public function committeeReject(int $stokvelId, int $loanId, int $committeeMemberId, string $reason): void
    {
        $application = $this->lockApplication($stokvelId, $loanId);
        if ($application['status'] !== self::STATUS_APPLIED) {
            throw new FinanceException('Only applied loans can be committee-rejected.');
        }

        $this->db->exec(
            'UPDATE loan_applications SET status = :status, decided_at = :decided_at, decided_by_user_id = :decided_by_user_id,
             decision_notes = :decision_notes, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => self::STATUS_COMMITTEE_REJECTED,
                'decided_at' => now_utc(),
                'decided_by_user_id' => $committeeMemberId,
                'decision_notes' => $reason,
                'updated_at' => now_utc(),
                'id' => $loanId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    public function disburse(int $stokvelId, int $loanId, int $disbursedBy, ?string $disbursementDate = null): void
    {
        $this->db->transaction(function () use ($stokvelId, $loanId, $disbursedBy, $disbursementDate): void {
            $application = $this->lockApplication($stokvelId, $loanId);
            if ($application['status'] !== self::STATUS_COMMITTEE_APPROVED) {
                throw new FinanceException('Loan must be committee-approved before disbursement.');
            }

            $existingLoan = $this->db->fetch(
                'SELECT id FROM loans WHERE loan_application_id = :application_id AND stokvel_id = :stokvel_id LIMIT 1',
                ['application_id' => $loanId, 'stokvel_id' => $stokvelId]
            );
            if ($existingLoan !== null) {
                throw new FinanceException('Loan already disbursed for this application.');
            }

            $product = $this->products->get($stokvelId, (int) $application['loan_product_id']);
            if ($product === null) {
                throw new FinanceException('Loan product not found.');
            }

            $amount = MoneyMath::normalize((string) $application['requested_amount']);
            $rate = MoneyMath::normalize((string) ($product['interest_rate_percent'] ?? $product['annual_interest_rate_percent'] ?? '0'));
            $disbursementDate = $disbursementDate ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
            $now = now_utc();

            $loanRecordId = (int) $this->db->insert(
                'INSERT INTO loans
                    (stokvel_id, loan_application_id, membership_id, loan_product_id, loan_ref, principal_amount,
                     interest_rate_percent, term_months, status, disbursed_at, disbursed_amount, outstanding_balance,
                     created_at, updated_at)
                 VALUES
                    (:stokvel_id, :loan_application_id, :membership_id, :loan_product_id, :loan_ref, :principal_amount,
                     :interest_rate_percent, :term_months, :status, :disbursed_at, :disbursed_amount, :outstanding_balance,
                     :created_at, :updated_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'loan_application_id' => $loanId,
                    'membership_id' => (int) $application['membership_id'],
                    'loan_product_id' => (int) $application['loan_product_id'],
                    'loan_ref' => $this->nextLoanRef($stokvelId),
                    'principal_amount' => $amount,
                    'interest_rate_percent' => $rate,
                    'term_months' => (int) $application['term_months'],
                    'status' => self::STATUS_ACTIVE,
                    'disbursed_at' => $now,
                    'disbursed_amount' => $amount,
                    'outstanding_balance' => $amount,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $schedule = $this->calculator->buildSchedule(
                $amount,
                $rate,
                (int) $application['term_months'],
                $this->resolveAmortizationMethod($product),
                (new \DateTimeImmutable($disbursementDate))->modify('+1 month')->format('Y-m-d'),
                '0.00'
            );
            $this->persistSchedule($stokvelId, $loanRecordId, $schedule);

            $bank = $this->chartOfAccounts->requireBySubType($stokvelId, 'bank');
            $loanReceivable = $this->chartOfAccounts->requireBySubType($stokvelId, 'loan_receivable');

            $journalId = $this->ledger->createDraft(
                $stokvelId,
                $disbursedBy,
                $disbursementDate,
                'LOAN-DISB-' . $loanRecordId,
                'Loan disbursement #' . $loanRecordId
            );
            $this->ledger->addLine(
                $stokvelId,
                $journalId,
                (int) $loanReceivable['id'],
                $amount,
                '0.00',
                (int) $application['membership_id'],
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
            $this->ledger->post($stokvelId, $journalId, $disbursedBy, true);
        });
    }

    public function repay(
        int $stokvelId,
        int $loanId,
        string $amount,
        int $recordedBy,
        ?string $paidAt = null,
        string $paymentMethod = 'cash'
    ): array {
        return $this->db->transaction(function () use ($stokvelId, $loanId, $amount, $recordedBy, $paidAt, $paymentMethod): array {
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
                    (int) $loan['membership_id'],
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
                    (int) $loan['membership_id'],
                    'Interest repayment'
                );
            }
            $this->ledger->post($stokvelId, $journalId, $recordedBy, true);

            $newBalance = MoneyMath::max(
                MoneyMath::sub(MoneyMath::normalize((string) $loan['outstanding_balance']), $amount),
                '0.00'
            );
            $status = MoneyMath::isZero($newBalance) ? self::STATUS_SETTLED : self::STATUS_ACTIVE;

            $this->db->exec(
                'UPDATE loans SET outstanding_balance = :outstanding_balance, status = :status, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'outstanding_balance' => $newBalance,
                    'status' => $status,
                    'updated_at' => now_utc(),
                    'id' => $loanId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            $this->db->insert(
                'INSERT INTO loan_repayments
                    (stokvel_id, loan_id, repayment_ref, amount, payment_method, paid_at, recorded_by_user_id, notes, created_at)
                 VALUES
                    (:stokvel_id, :loan_id, :repayment_ref, :amount, :payment_method, :paid_at, :recorded_by_user_id, :notes, :created_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'loan_id' => $loanId,
                    'repayment_ref' => $this->nextRepaymentRef($stokvelId),
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'paid_at' => $paidAt,
                    'recorded_by_user_id' => $recordedBy,
                    'notes' => json_encode($allocation, JSON_THROW_ON_ERROR),
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

        $balance = MoneyMath::normalize((string) $loan['outstanding_balance']);
        if (!MoneyMath::isPositive($balance)) {
            throw new FinanceException('Loan has no outstanding balance.');
        }

        $this->repay($stokvelId, $loanId, $balance, $recordedBy);

        return ['settlement_total' => $balance];
    }

    public function flagArrears(int $stokvelId, ?string $asOfDate = null): int
    {
        $asOfDate = $asOfDate ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $overdue = $this->db->fetchAll(
            'SELECT id FROM loan_schedules
             WHERE stokvel_id = :stokvel_id AND status IN (\'pending\', \'partial\', \'overdue\') AND due_date < :as_of_date',
            ['stokvel_id' => $stokvelId, 'as_of_date' => $asOfDate]
        );

        $count = 0;
        foreach ($overdue as $row) {
            $this->db->exec(
                'UPDATE loan_schedules SET status = :status, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                ['status' => 'overdue', 'updated_at' => now_utc(), 'id' => $row['id'], 'stokvel_id' => $stokvelId]
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

            $outstanding = MoneyMath::normalize((string) $loan['outstanding_balance']);
            $rate = MoneyMath::normalize($newRatePercent ?? (string) $loan['interest_rate_percent']);

            $this->db->exec(
                'UPDATE loans SET status = :status, updated_at = :updated_at WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_RESTRUCTURED,
                    'updated_at' => now_utc(),
                    'id' => $loanId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            $applicationId = $this->apply(
                $stokvelId,
                (int) $loan['membership_id'],
                (int) $loan['loan_product_id'],
                $outstanding,
                $newTermMonths,
                $restructuredBy,
                'Restructured from loan #' . $loanId . ': ' . $reason
            );
            $this->committeeApprove($stokvelId, $applicationId, $restructuredBy, $outstanding, $reason);
            $this->disburse($stokvelId, $applicationId, $restructuredBy);

            $newLoan = $this->db->fetch(
                'SELECT id FROM loans WHERE loan_application_id = :application_id AND stokvel_id = :stokvel_id LIMIT 1',
                ['application_id' => $applicationId, 'stokvel_id' => $stokvelId]
            );

            if ($newLoan !== null) {
                $this->db->exec(
                    'UPDATE loans SET interest_rate_percent = :rate, updated_at = :updated_at
                     WHERE id = :id AND stokvel_id = :stokvel_id',
                    [
                        'rate' => $rate,
                        'updated_at' => now_utc(),
                        'id' => $newLoan['id'],
                        'stokvel_id' => $stokvelId,
                    ]
                );

                return (int) $newLoan['id'];
            }

            throw new FinanceException('Restructured loan record was not created.');
        });
    }

    public function writeOff(int $stokvelId, int $loanId, int $writtenOffBy, string $reason): void
    {
        $this->db->transaction(function () use ($stokvelId, $loanId, $writtenOffBy, $reason): void {
            $loan = $this->lockLoan($stokvelId, $loanId);
            if (!in_array($loan['status'], [self::STATUS_ACTIVE, self::STATUS_DISBURSED], true)) {
                throw new FinanceException('Loan cannot be written off in its current status.');
            }

            $totalOutstanding = MoneyMath::normalize((string) $loan['outstanding_balance']);
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
                (int) $loan['membership_id'],
                'Loan receivable written off'
            );
            $this->ledger->post($stokvelId, $journalId, $writtenOffBy, true);

            $this->db->exec(
                'UPDATE loans SET status = :status, outstanding_balance = :zero, closed_at = :closed_at, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_WRITTEN_OFF,
                    'zero' => '0.00',
                    'closed_at' => now_utc(),
                    'updated_at' => now_utc(),
                    'id' => $loanId,
                    'stokvel_id' => $stokvelId,
                ]
            );
        });
    }

    public function getLoan(int $stokvelId, int $loanId): ?array
    {
        $loan = $this->db->fetch(
            'SELECT * FROM loans WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $loanId, 'stokvel_id' => $stokvelId]
        );
        if ($loan !== null) {
            $loan['member_id'] = $loan['membership_id'];

            return $loan;
        }

        $application = $this->db->fetch(
            'SELECT * FROM loan_applications WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $loanId, 'stokvel_id' => $stokvelId]
        );
        if ($application === null) {
            return null;
        }

        $application['member_id'] = $application['membership_id'];
        $application['requested_amount'] = $application['requested_amount'];

        return $application;
    }

    public function getSchedule(int $stokvelId, int $loanId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM loan_schedules
             WHERE loan_id = :loan_id AND stokvel_id = :stokvel_id
             ORDER BY instalment_number ASC',
            ['loan_id' => $loanId, 'stokvel_id' => $stokvelId]
        );

        $balance = null;
        foreach ($rows as &$row) {
            $opening = $balance ?? MoneyMath::add((string) $row['principal_due'], (string) $row['interest_due']);
            $row['opening_balance'] = $opening;
            $row['principal_amount'] = $row['principal_due'];
            $row['interest_amount'] = $row['interest_due'];
            $row['fee_amount'] = '0.00';
            $row['total_instalment'] = $row['total_due'];
            $row['paid_amount'] = $row['amount_paid'];
            $row['closing_balance'] = MoneyMath::max(MoneyMath::sub($opening, (string) $row['principal_due']), '0.00');
            $balance = $row['closing_balance'];
        }
        unset($row);

        return $rows;
    }

    /** @param list<array<string, mixed>> $schedule */
    private function persistSchedule(int $stokvelId, int $loanId, array $schedule): void
    {
        $now = now_utc();
        foreach ($schedule as $row) {
            $principal = MoneyMath::normalize((string) $row['principal']);
            $interest = MoneyMath::normalize((string) $row['interest']);
            $fee = MoneyMath::normalize((string) ($row['fee'] ?? '0.00'));
            $total = MoneyMath::normalize((string) $row['total_instalment']);

            $this->db->insert(
                'INSERT INTO loan_schedules
                    (stokvel_id, loan_id, instalment_number, due_date, principal_due, interest_due, total_due,
                     amount_paid, status, created_at, updated_at)
                 VALUES
                    (:stokvel_id, :loan_id, :instalment_number, :due_date, :principal_due, :interest_due, :total_due,
                     :amount_paid, :status, :created_at, :updated_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'loan_id' => $loanId,
                    'instalment_number' => $row['instalment_number'],
                    'due_date' => $row['due_date'],
                    'principal_due' => MoneyMath::add($principal, $fee),
                    'interest_due' => $interest,
                    'total_due' => $total,
                    'amount_paid' => '0.00',
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
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
            'SELECT * FROM loan_schedules
             WHERE loan_id = :loan_id AND stokvel_id = :stokvel_id AND status IN (\'pending\', \'partial\', \'overdue\')
             ORDER BY due_date ASC, instalment_number ASC FOR UPDATE',
            ['loan_id' => $loanId, 'stokvel_id' => $stokvelId]
        );

        foreach ($instalments as $instalment) {
            if (!MoneyMath::isPositive($remaining)) {
                break;
            }

            $instalmentDue = MoneyMath::sub((string) $instalment['total_due'], (string) $instalment['amount_paid']);
            if (!MoneyMath::isPositive($instalmentDue)) {
                continue;
            }

            $apply = MoneyMath::min($remaining, $instalmentDue);
            $remaining = MoneyMath::sub($remaining, $apply);

            $interestDue = (string) $instalment['interest_due'];
            $principalDue = (string) $instalment['principal_due'];
            $componentTotal = MoneyMath::add($interestDue, $principalDue);

            if (MoneyMath::isZero($componentTotal)) {
                $allocation['principal'] = MoneyMath::add($allocation['principal'], $apply);
            } else {
                $toInterest = MoneyMath::div(MoneyMath::mul($apply, $interestDue), $componentTotal);
                $toPrincipal = MoneyMath::sub($apply, $toInterest);
                $allocation['interest'] = MoneyMath::add($allocation['interest'], $toInterest);
                $allocation['principal'] = MoneyMath::add($allocation['principal'], $toPrincipal);
            }

            $newPaid = MoneyMath::add((string) $instalment['amount_paid'], $apply);
            $status = MoneyMath::comp($newPaid, (string) $instalment['total_due']) >= 0 ? 'paid' : 'partial';
            $this->db->exec(
                'UPDATE loan_schedules SET amount_paid = :amount_paid, status = :status, paid_at = :paid_at, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'amount_paid' => $newPaid,
                    'status' => $status,
                    'paid_at' => $status === 'paid' ? now_utc() : null,
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

    private function lockApplication(int $stokvelId, int $applicationId): array
    {
        $application = $this->db->fetch(
            'SELECT * FROM loan_applications WHERE id = :id AND stokvel_id = :stokvel_id FOR UPDATE',
            ['id' => $applicationId, 'stokvel_id' => $stokvelId]
        );
        if ($application === null) {
            throw new FinanceException('Loan application not found.');
        }

        return $application;
    }

    /** @param array<string, mixed> $product */
    private function resolveAmortizationMethod(array $product): string
    {
        $method = (string) ($product['amortization_method'] ?? $product['interest_type'] ?? LoanScheduleCalculator::METHOD_REDUCING_BALANCE);
        if ($method === 'reducing') {
            return LoanScheduleCalculator::METHOD_REDUCING_BALANCE;
        }
        if ($method === 'flat') {
            return LoanScheduleCalculator::METHOD_FLAT;
        }

        return $method;
    }

    private function nextApplicationRef(int $stokvelId): string
    {
        $year = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y');
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM loan_applications WHERE stokvel_id = :stokvel_id AND application_ref LIKE :prefix',
            ['stokvel_id' => $stokvelId, 'prefix' => 'APP-' . $stokvelId . '-' . $year . '-%']
        );

        return sprintf('APP-%d-%s-%06d', $stokvelId, $year, ((int) ($row['cnt'] ?? 0)) + 1);
    }

    private function nextLoanRef(int $stokvelId): string
    {
        $year = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y');
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM loans WHERE stokvel_id = :stokvel_id AND loan_ref LIKE :prefix',
            ['stokvel_id' => $stokvelId, 'prefix' => 'LOAN-' . $stokvelId . '-' . $year . '-%']
        );

        return sprintf('LOAN-%d-%s-%06d', $stokvelId, $year, ((int) ($row['cnt'] ?? 0)) + 1);
    }

    private function nextRepaymentRef(int $stokvelId): string
    {
        $year = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y');
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM loan_repayments WHERE stokvel_id = :stokvel_id AND repayment_ref LIKE :prefix',
            ['stokvel_id' => $stokvelId, 'prefix' => 'REP-' . $stokvelId . '-' . $year . '-%']
        );

        return sprintf('REP-%d-%s-%06d', $stokvelId, $year, ((int) ($row['cnt'] ?? 0)) + 1);
    }
}
