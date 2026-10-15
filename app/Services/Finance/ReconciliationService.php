<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

final class ReconciliationService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly Database $db
    ) {
    }

    public function start(
        int $stokvelId,
        int $bankAccountId,
        string $periodStart,
        string $periodEnd,
        string $statementOpeningBalance,
        string $statementClosingBalance,
        int $startedBy
    ): int {
        $account = $this->db->fetch(
            'SELECT id FROM chart_of_accounts
             WHERE id = :id AND stokvel_id = :stokvel_id AND sub_type IN (\'bank\', \'cash\')',
            ['id' => $bankAccountId, 'stokvel_id' => $stokvelId]
        );
        if ($account === null) {
            throw new FinanceException('Invalid bank/cash account.');
        }

        $now = now_utc();
        return (int) $this->db->insert(
            'INSERT INTO bank_reconciliations
                (stokvel_id, bank_account_id, period_start, period_end,
                 statement_opening_balance, statement_closing_balance, book_opening_balance, book_closing_balance,
                 unmatched_difference, status, started_by, created_at, updated_at)
             VALUES
                (:stokvel_id, :bank_account_id, :period_start, :period_end,
                 :stmt_open, :stmt_close, :book_open, :book_close,
                 :difference, :status, :started_by, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'bank_account_id' => $bankAccountId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'stmt_open' => MoneyMath::normalize($statementOpeningBalance),
                'stmt_close' => MoneyMath::normalize($statementClosingBalance),
                'book_open' => '0.00',
                'book_close' => '0.00',
                'difference' => '0.00',
                'status' => self::STATUS_IN_PROGRESS,
                'started_by' => $startedBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function refreshBookBalances(int $stokvelId, int $reconciliationId): array
    {
        $recon = $this->getReconciliation($stokvelId, $reconciliationId);
        if ($recon === null) {
            throw new FinanceException('Reconciliation not found.');
        }

        $opening = $this->sumPostedLinesBefore($stokvelId, (int) $recon['bank_account_id'], (string) $recon['period_start']);
        $closing = $this->sumPostedLinesUpTo($stokvelId, (int) $recon['bank_account_id'], (string) $recon['period_end']);
        $matched = $this->sumMatchedStatementItems($stokvelId, $reconciliationId);
        $statementAdjusted = MoneyMath::add((string) $recon['statement_closing_balance'], $matched['adjustments']);
        $difference = MoneyMath::sub($closing, $statementAdjusted);

        $this->db->exec(
            'UPDATE bank_reconciliations
             SET book_opening_balance = :book_open, book_closing_balance = :book_close,
                 unmatched_difference = :difference, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'book_open' => $opening,
                'book_close' => $closing,
                'difference' => $difference,
                'updated_at' => now_utc(),
                'id' => $reconciliationId,
                'stokvel_id' => $stokvelId,
            ]
        );

        return [
            'book_opening_balance' => $opening,
            'book_closing_balance' => $closing,
            'unmatched_difference' => $difference,
        ];
    }

    public function addStatementItem(
        int $stokvelId,
        int $reconciliationId,
        string $transactionDate,
        string $description,
        string $amount,
        ?string $reference = null
    ): int {
        $recon = $this->getReconciliation($stokvelId, $reconciliationId);
        if ($recon === null || $recon['status'] !== self::STATUS_IN_PROGRESS) {
            throw new FinanceException('Reconciliation is not in progress.');
        }

        return (int) $this->db->insert(
            'INSERT INTO reconciliation_statement_items
                (stokvel_id, reconciliation_id, transaction_date, description, reference, amount, matched, created_at, updated_at)
             VALUES
                (:stokvel_id, :reconciliation_id, :transaction_date, :description, :reference, :amount, 0, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'reconciliation_id' => $reconciliationId,
                'transaction_date' => $transactionDate,
                'description' => $description,
                'reference' => $reference,
                'amount' => MoneyMath::normalize($amount),
                'created_at' => now_utc(),
                'updated_at' => now_utc(),
            ]
        );
    }

    public function matchStatementToJournalLine(
        int $stokvelId,
        int $reconciliationId,
        int $statementItemId,
        int $journalLineId,
        int $matchedBy
    ): void {
        $this->db->transaction(function () use ($stokvelId, $reconciliationId, $statementItemId, $journalLineId, $matchedBy): void {
            $recon = $this->db->fetch(
                'SELECT * FROM bank_reconciliations WHERE id = :id AND stokvel_id = :stokvel_id FOR UPDATE',
                ['id' => $reconciliationId, 'stokvel_id' => $stokvelId]
            );
            if ($recon === null || $recon['status'] !== self::STATUS_IN_PROGRESS) {
                throw new FinanceException('Reconciliation is not in progress.');
            }

            $statement = $this->db->fetch(
                'SELECT * FROM reconciliation_statement_items
                 WHERE id = :id AND reconciliation_id = :reconciliation_id AND stokvel_id = :stokvel_id',
                ['id' => $statementItemId, 'reconciliation_id' => $reconciliationId, 'stokvel_id' => $stokvelId]
            );
            if ($statement === null) {
                throw new FinanceException('Statement item not found.');
            }

            $line = $this->db->fetch(
                'SELECT jl.*, je.status AS journal_status, je.entry_date
                 FROM journal_lines jl
                 INNER JOIN journal_entries je ON je.id = jl.journal_entry_id AND je.stokvel_id = jl.stokvel_id
                 WHERE jl.id = :id AND jl.stokvel_id = :stokvel_id AND jl.account_id = :account_id',
                [
                    'id' => $journalLineId,
                    'stokvel_id' => $stokvelId,
                    'account_id' => $recon['bank_account_id'],
                ]
            );
            if ($line === null || $line['journal_status'] !== LedgerService::STATUS_POSTED) {
                throw new FinanceException('Invalid journal line for reconciliation.');
            }

            $lineAmount = MoneyMath::sub((string) $line['debit'], (string) $line['credit']);
            if (!MoneyMath::equals($lineAmount, (string) $statement['amount'])) {
                throw new FinanceException('Statement amount does not match journal line net amount.');
            }

            $this->db->insert(
                'INSERT INTO reconciliation_matches
                    (stokvel_id, reconciliation_id, statement_item_id, journal_line_id, matched_by, matched_at, created_at)
                 VALUES
                    (:stokvel_id, :reconciliation_id, :statement_item_id, :journal_line_id, :matched_by, :matched_at, :created_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'reconciliation_id' => $reconciliationId,
                    'statement_item_id' => $statementItemId,
                    'journal_line_id' => $journalLineId,
                    'matched_by' => $matchedBy,
                    'matched_at' => now_utc(),
                    'created_at' => now_utc(),
                ]
            );

            $this->db->exec(
                'UPDATE reconciliation_statement_items SET matched = 1, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                ['updated_at' => now_utc(), 'id' => $statementItemId, 'stokvel_id' => $stokvelId]
            );

            $this->refreshBookBalances($stokvelId, $reconciliationId);
        });
    }

    public function complete(int $stokvelId, int $reconciliationId, int $completedBy): void
    {
        $balances = $this->refreshBookBalances($stokvelId, $reconciliationId);
        if (!MoneyMath::isZero($balances['unmatched_difference'])) {
            throw new FinanceException('Cannot complete reconciliation while difference is not zero.');
        }

        $unmatched = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM reconciliation_statement_items
             WHERE reconciliation_id = :reconciliation_id AND stokvel_id = :stokvel_id AND matched = 0',
            ['reconciliation_id' => $reconciliationId, 'stokvel_id' => $stokvelId]
        );
        if ($unmatched !== null && (int) $unmatched['cnt'] > 0) {
            throw new FinanceException('All statement items must be matched before completion.');
        }

        $this->db->exec(
            'UPDATE bank_reconciliations
             SET status = :status, completed_by = :completed_by, completed_at = :completed_at, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => self::STATUS_COMPLETED,
                'completed_by' => $completedBy,
                'completed_at' => now_utc(),
                'updated_at' => now_utc(),
                'id' => $reconciliationId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    public function getReconciliation(int $stokvelId, int $reconciliationId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM bank_reconciliations WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $reconciliationId, 'stokvel_id' => $stokvelId]
        );
    }

    private function sumPostedLinesBefore(int $stokvelId, int $accountId, string $beforeDate): string
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(jl.debit - jl.credit), 0) AS balance
             FROM journal_lines jl
             INNER JOIN journal_entries je ON je.id = jl.journal_entry_id AND je.stokvel_id = jl.stokvel_id
             WHERE jl.stokvel_id = :stokvel_id AND jl.account_id = :account_id
               AND je.status = :posted AND je.entry_date < :before_date',
            [
                'stokvel_id' => $stokvelId,
                'account_id' => $accountId,
                'posted' => LedgerService::STATUS_POSTED,
                'before_date' => $beforeDate,
            ]
        );

        return MoneyMath::normalize($row['balance'] ?? '0');
    }

    private function sumPostedLinesUpTo(int $stokvelId, int $accountId, string $upToDate): string
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(jl.debit - jl.credit), 0) AS balance
             FROM journal_lines jl
             INNER JOIN journal_entries je ON je.id = jl.journal_entry_id AND je.stokvel_id = jl.stokvel_id
             WHERE jl.stokvel_id = :stokvel_id AND jl.account_id = :account_id
               AND je.status = :posted AND je.entry_date <= :up_to_date',
            [
                'stokvel_id' => $stokvelId,
                'account_id' => $accountId,
                'posted' => LedgerService::STATUS_POSTED,
                'up_to_date' => $upToDate,
            ]
        );

        return MoneyMath::normalize($row['balance'] ?? '0');
    }

    /** @return array{adjustments: string} */
    private function sumMatchedStatementItems(int $stokvelId, int $reconciliationId): array
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM reconciliation_statement_items
             WHERE stokvel_id = :stokvel_id AND reconciliation_id = :reconciliation_id AND matched = 0',
            ['stokvel_id' => $stokvelId, 'reconciliation_id' => $reconciliationId]
        );

        return ['adjustments' => MoneyMath::normalize($row['total'] ?? '0')];
    }
}
