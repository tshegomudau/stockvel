<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

final class ExpenseService
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_PAID = 'paid';
    public const STATUS_REVERSED = 'reversed';

    public function __construct(
        private readonly Database $db,
        private readonly LedgerService $ledger,
        private readonly ChartOfAccountsService $chartOfAccounts
    ) {
    }

    public function createCategory(
        int $stokvelId,
        string $name,
        int $expenseAccountId,
        ?string $budgetAmount = null
    ): int {
        $account = $this->db->fetch(
            'SELECT id FROM chart_of_accounts
             WHERE id = :id AND stokvel_id = :stokvel_id AND account_type = :type',
            ['id' => $expenseAccountId, 'stokvel_id' => $stokvelId, 'type' => 'expense']
        );
        if ($account === null) {
            throw new FinanceException('Invalid expense account.');
        }

        $now = now_utc();
        return (int) $this->db->insert(
            'INSERT INTO expense_categories
                (stokvel_id, name, account_id, budget_amount, is_active, created_at, updated_at)
             VALUES
                (:stokvel_id, :name, :account_id, :budget_amount, 1, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'name' => $name,
                'account_id' => $expenseAccountId,
                'budget_amount' => $budgetAmount !== null ? MoneyMath::normalize($budgetAmount) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function listCategories(int $stokvelId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM expense_categories WHERE stokvel_id = :stokvel_id AND is_active = 1 ORDER BY name ASC',
            ['stokvel_id' => $stokvelId]
        );
    }

    public function requestExpense(
        int $stokvelId,
        int $categoryId,
        string $amount,
        string $description,
        int $requestedBy,
        ?string $expenseDate = null
    ): int {
        $category = $this->getCategory($stokvelId, $categoryId);
        if ($category === null) {
            throw new FinanceException('Expense category not found.');
        }

        $amount = MoneyMath::normalize($amount);
        if (!MoneyMath::isPositive($amount)) {
            throw new FinanceException('Expense amount must be positive.');
        }

        $comparison = $this->compareToBudget($stokvelId, $categoryId, $amount);
        $now = now_utc();

        return (int) $this->db->insert(
            'INSERT INTO expenses
                (stokvel_id, category_id, amount, description, expense_date, status,
                 requested_by, budget_snapshot, budget_remaining_snapshot, created_at, updated_at)
             VALUES
                (:stokvel_id, :category_id, :amount, :description, :expense_date, :status,
                 :requested_by, :budget_snapshot, :budget_remaining, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'category_id' => $categoryId,
                'amount' => $amount,
                'description' => $description,
                'expense_date' => $expenseDate ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d'),
                'status' => self::STATUS_REQUESTED,
                'requested_by' => $requestedBy,
                'budget_snapshot' => $comparison['budget'],
                'budget_remaining' => $comparison['remaining_after'],
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function approve(int $stokvelId, int $expenseId, int $approvedBy): void
    {
        $expense = $this->lockExpense($stokvelId, $expenseId);
        if ($expense['status'] !== self::STATUS_REQUESTED) {
            throw new FinanceException('Only requested expenses can be approved.');
        }
        if ((int) $expense['requested_by'] === $approvedBy && $this->expenseMakerCheckerEnabled($stokvelId)) {
            throw new AuthorizationException('Maker-checker: you cannot approve your own expense request.');
        }

        $this->db->exec(
            'UPDATE expenses SET status = :status, approved_by = :approved_by, approved_at = :approved_at, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => self::STATUS_APPROVED,
                'approved_by' => $approvedBy,
                'approved_at' => now_utc(),
                'updated_at' => now_utc(),
                'id' => $expenseId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    public function reject(int $stokvelId, int $expenseId, int $rejectedBy, string $reason): void
    {
        $expense = $this->lockExpense($stokvelId, $expenseId);
        if ($expense['status'] !== self::STATUS_REQUESTED) {
            throw new FinanceException('Only requested expenses can be rejected.');
        }

        $this->db->exec(
            'UPDATE expenses SET status = :status, rejected_by = :rejected_by, rejection_reason = :reason,
             rejected_at = :rejected_at, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => self::STATUS_REJECTED,
                'rejected_by' => $rejectedBy,
                'reason' => $reason,
                'rejected_at' => now_utc(),
                'updated_at' => now_utc(),
                'id' => $expenseId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    public function pay(int $stokvelId, int $expenseId, int $paidBy): void
    {
        $this->db->transaction(function () use ($stokvelId, $expenseId, $paidBy): void {
            $expense = $this->lockExpense($stokvelId, $expenseId);
            if ($expense['status'] !== self::STATUS_APPROVED) {
                throw new FinanceException('Only approved expenses can be paid.');
            }

            $category = $this->getCategory($stokvelId, (int) $expense['category_id']);
            if ($category === null) {
                throw new FinanceException('Expense category not found.');
            }

            $bank = $this->chartOfAccounts->requireBySubType($stokvelId, 'bank');
            $amount = MoneyMath::normalize((string) $expense['amount']);

            $journalId = $this->ledger->createDraft(
                $stokvelId,
                $paidBy,
                (string) $expense['expense_date'],
                'EXP-' . $expenseId,
                (string) $expense['description']
            );
            $this->ledger->addLine(
                $stokvelId,
                $journalId,
                (int) $category['account_id'],
                $amount,
                '0.00',
                null,
                'Expense payment'
            );
            $this->ledger->addLine(
                $stokvelId,
                $journalId,
                (int) $bank['id'],
                '0.00',
                $amount,
                null,
                'Bank payment'
            );
            $this->ledger->post($stokvelId, $journalId, $paidBy);

            $this->db->exec(
                'UPDATE expenses SET status = :status, paid_by = :paid_by, paid_at = :paid_at,
                 journal_entry_id = :journal_id, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_PAID,
                    'paid_by' => $paidBy,
                    'paid_at' => now_utc(),
                    'journal_id' => $journalId,
                    'updated_at' => now_utc(),
                    'id' => $expenseId,
                    'stokvel_id' => $stokvelId,
                ]
            );
        });
    }

    public function reverse(int $stokvelId, int $expenseId, int $reversedBy, string $reason): void
    {
        $expense = $this->getExpense($stokvelId, $expenseId);
        if ($expense === null || $expense['status'] !== self::STATUS_PAID) {
            throw new FinanceException('Only paid expenses can be reversed.');
        }
        if ($expense['journal_entry_id'] === null) {
            throw new FinanceException('Expense has no linked journal entry.');
        }

        $reversalJournalId = $this->ledger->reverse($stokvelId, (int) $expense['journal_entry_id'], $reversedBy, $reason);

        $this->db->exec(
            'UPDATE expenses SET status = :status, reversed_by = :reversed_by, reversed_at = :reversed_at,
             reversal_journal_entry_id = :journal_id, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => self::STATUS_REVERSED,
                'reversed_by' => $reversedBy,
                'reversed_at' => now_utc(),
                'journal_id' => $reversalJournalId,
                'updated_at' => now_utc(),
                'id' => $expenseId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    /** @return array{budget: ?string, spent: string, remaining: ?string, remaining_after: ?string, exceeds: bool} */
    public function compareToBudget(int $stokvelId, int $categoryId, string $proposedAmount): array
    {
        $category = $this->getCategory($stokvelId, $categoryId);
        if ($category === null) {
            throw new FinanceException('Expense category not found.');
        }

        $budget = $category['budget_amount'] !== null ? MoneyMath::normalize((string) $category['budget_amount']) : null;
        $yearStart = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y') . '-01-01';
        $spentRow = $this->db->fetch(
            'SELECT COALESCE(SUM(amount), 0) AS spent FROM expenses
             WHERE stokvel_id = :stokvel_id AND category_id = :category_id
               AND status IN (\'paid\', \'approved\', \'requested\') AND expense_date >= :year_start',
            [
                'stokvel_id' => $stokvelId,
                'category_id' => $categoryId,
                'year_start' => $yearStart,
            ]
        );
        $spent = MoneyMath::normalize($spentRow['spent'] ?? '0');
        $proposedAmount = MoneyMath::normalize($proposedAmount);

        if ($budget === null) {
            return [
                'budget' => null,
                'spent' => $spent,
                'remaining' => null,
                'remaining_after' => null,
                'exceeds' => false,
            ];
        }

        $remaining = MoneyMath::sub($budget, $spent);
        $remainingAfter = MoneyMath::sub($remaining, $proposedAmount);

        return [
            'budget' => $budget,
            'spent' => $spent,
            'remaining' => $remaining,
            'remaining_after' => $remainingAfter,
            'exceeds' => MoneyMath::comp($remainingAfter, '0') < 0,
        ];
    }

    public function getExpense(int $stokvelId, int $expenseId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM expenses WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $expenseId, 'stokvel_id' => $stokvelId]
        );
    }

    private function getCategory(int $stokvelId, int $categoryId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM expense_categories WHERE id = :id AND stokvel_id = :stokvel_id AND is_active = 1',
            ['id' => $categoryId, 'stokvel_id' => $stokvelId]
        );
    }

    private function lockExpense(int $stokvelId, int $expenseId): array
    {
        $expense = $this->db->fetch(
            'SELECT * FROM expenses WHERE id = :id AND stokvel_id = :stokvel_id FOR UPDATE',
            ['id' => $expenseId, 'stokvel_id' => $stokvelId]
        );
        if ($expense === null) {
            throw new FinanceException('Expense not found.');
        }

        return $expense;
    }

    private function expenseMakerCheckerEnabled(int $stokvelId): bool
    {
        $row = $this->db->fetch(
            'SELECT expense_maker_checker FROM stokvel_settings WHERE stokvel_id = :stokvel_id LIMIT 1',
            ['stokvel_id' => $stokvelId]
        );

        return $row === null || (int) ($row['expense_maker_checker'] ?? 1) === 1;
    }
}
