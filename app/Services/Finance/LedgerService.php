<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Exceptions\AuthorizationException;
use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

final class LedgerService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVERSED = 'reversed';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        private readonly Database $db
    ) {
    }

    public function createDraft(
        int $stokvelId,
        int $createdBy,
        string $entryDate,
        string $reference,
        string $description = '',
        ?string $idempotencyKey = null
    ): int {
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = $this->findByIdempotencyKey($stokvelId, $idempotencyKey);
            if ($existing !== null) {
                return (int) $existing['id'];
            }
        }

        $now = now_utc();
        $entryNumber = $this->uniqueEntryNumber($stokvelId, $reference);
        $journalId = (int) $this->db->insert(
            'INSERT INTO journal_entries
                (stokvel_id, entry_number, entry_date, description, status,
                 source_type, source_id, created_by_user_id, created_at, updated_at)
             VALUES
                (:stokvel_id, :entry_number, :entry_date, :description, :status,
                 :source_type, :source_id, :created_by_user_id, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate,
                'description' => $description !== '' ? $description : $reference,
                'status' => self::STATUS_DRAFT,
                'source_type' => $idempotencyKey !== null && $idempotencyKey !== '' ? 'idempotency' : null,
                'source_id' => null,
                'created_by_user_id' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $this->storeIdempotencyKey($stokvelId, 'journal_entry', $idempotencyKey, (string) $journalId);
            $this->db->exec(
                'UPDATE journal_entries SET source_id = :source_id, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'source_id' => $journalId,
                    'updated_at' => $now,
                    'id' => $journalId,
                    'stokvel_id' => $stokvelId,
                ]
            );
        }

        return $journalId;
    }

    public function addLine(
        int $stokvelId,
        int $journalEntryId,
        int $accountId,
        string $debit,
        string $credit,
        ?int $memberId = null,
        string $lineDescription = ''
    ): int {
        $this->requireEditableEntry($stokvelId, $journalEntryId);

        $debit = MoneyMath::normalize($debit);
        $credit = MoneyMath::normalize($credit);
        if (MoneyMath::isPositive($debit) && MoneyMath::isPositive($credit)) {
            throw new FinanceException('A line cannot have both debit and credit amounts.');
        }
        if (MoneyMath::isZero($debit) && MoneyMath::isZero($credit)) {
            throw new FinanceException('A line must have a debit or credit amount.');
        }

        $account = $this->db->fetch(
            'SELECT id FROM chart_of_accounts WHERE id = :id AND stokvel_id = :stokvel_id AND is_active = 1',
            ['id' => $accountId, 'stokvel_id' => $stokvelId]
        );
        if ($account === null) {
            throw new FinanceException('Invalid account for this stokvel.');
        }

        if ($memberId !== null) {
            $member = $this->db->fetch(
                'SELECT id FROM memberships WHERE id = :id AND stokvel_id = :stokvel_id',
                ['id' => $memberId, 'stokvel_id' => $stokvelId]
            );
            if ($member === null) {
                throw new FinanceException('Invalid member for this stokvel.');
            }
        }

        $lineOrder = (int) ($this->db->fetch(
            'SELECT COALESCE(MAX(line_number), 0) AS max_order FROM journal_lines
             WHERE journal_entry_id = :journal_entry_id AND stokvel_id = :stokvel_id',
            ['journal_entry_id' => $journalEntryId, 'stokvel_id' => $stokvelId]
        )['max_order'] ?? 0) + 1;

        return (int) $this->db->insert(
            'INSERT INTO journal_lines
                (stokvel_id, journal_entry_id, chart_of_account_id, membership_id, debit, credit, description, line_number, created_at)
             VALUES
                (:stokvel_id, :journal_entry_id, :chart_of_account_id, :membership_id, :debit, :credit, :description, :line_number, :created_at)',
            [
                'stokvel_id' => $stokvelId,
                'journal_entry_id' => $journalEntryId,
                'chart_of_account_id' => $accountId,
                'membership_id' => $memberId,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $lineDescription !== '' ? $lineDescription : null,
                'line_number' => $lineOrder,
                'created_at' => now_utc(),
            ]
        );
    }

    public function submitForApproval(int $stokvelId, int $journalEntryId, int $submittedBy): void
    {
        $this->db->transaction(function () use ($stokvelId, $journalEntryId): void {
            $entry = $this->lockEntry($stokvelId, $journalEntryId);
            if ($entry['status'] !== self::STATUS_DRAFT) {
                throw new FinanceException('Only draft entries can be submitted for approval.');
            }
            $this->assertBalanced($stokvelId, $journalEntryId);

            $this->db->exec(
                'UPDATE journal_entries SET status = :status, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_PENDING_APPROVAL,
                    'updated_at' => now_utc(),
                    'id' => $journalEntryId,
                    'stokvel_id' => $stokvelId,
                ]
            );
        });
    }

    public function approve(int $stokvelId, int $journalEntryId, int $approvedBy): void
    {
        $this->db->transaction(function () use ($stokvelId, $journalEntryId, $approvedBy): void {
            $entry = $this->lockEntry($stokvelId, $journalEntryId);
            if ($entry['status'] !== self::STATUS_PENDING_APPROVAL) {
                throw new FinanceException('Only pending entries can be approved.');
            }
            $createdBy = (int) ($entry['created_by_user_id'] ?? 0);
            if ($this->isMakerCheckerEnabled($stokvelId) && $createdBy === $approvedBy) {
                throw new AuthorizationException('Maker-checker: you cannot approve your own journal entry.');
            }
            $this->assertBalanced($stokvelId, $journalEntryId);

            $this->db->exec(
                'UPDATE journal_entries
                 SET status = :status, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_PENDING_APPROVAL,
                    'updated_at' => now_utc(),
                    'id' => $journalEntryId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            // Record approval actor via source metadata for auditability without schema drift.
            $this->db->exec(
                'UPDATE journal_entries
                 SET source_type = :source_type, source_id = :source_id, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'source_type' => 'approved_by',
                    'source_id' => $approvedBy,
                    'updated_at' => now_utc(),
                    'id' => $journalEntryId,
                    'stokvel_id' => $stokvelId,
                ]
            );
        });
    }

    public function reject(int $stokvelId, int $journalEntryId, int $rejectedBy, string $reason): void
    {
        $this->db->transaction(function () use ($stokvelId, $journalEntryId, $rejectedBy, $reason): void {
            $entry = $this->lockEntry($stokvelId, $journalEntryId);
            if ($entry['status'] !== self::STATUS_PENDING_APPROVAL) {
                throw new FinanceException('Only pending entries can be rejected.');
            }
            $createdBy = (int) ($entry['created_by_user_id'] ?? 0);
            if ($this->isMakerCheckerEnabled($stokvelId) && $createdBy === $rejectedBy) {
                throw new AuthorizationException('Maker-checker: you cannot reject your own journal entry.');
            }

            $this->db->exec(
                'UPDATE journal_entries
                 SET status = :status, description = :description, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_REJECTED,
                    'description' => 'Rejected: ' . $reason,
                    'updated_at' => now_utc(),
                    'id' => $journalEntryId,
                    'stokvel_id' => $stokvelId,
                ]
            );
        });
    }

    /**
     * @param bool $allowOperationalPost When true, skips maker-checker (used for contribution/expense operational postings).
     */
    public function post(int $stokvelId, int $journalEntryId, int $postedBy, bool $allowOperationalPost = false): void
    {
        $this->db->transaction(function () use ($stokvelId, $journalEntryId, $postedBy, $allowOperationalPost): void {
            $entry = $this->lockEntry($stokvelId, $journalEntryId);
            if (!in_array($entry['status'], [self::STATUS_DRAFT, self::STATUS_PENDING_APPROVAL], true)) {
                throw new FinanceException('Only draft or pending entries can be posted.');
            }

            $makerChecker = $this->isMakerCheckerEnabled($stokvelId);
            if ($makerChecker && !$allowOperationalPost) {
                if ($entry['status'] === self::STATUS_DRAFT) {
                    throw new FinanceException('Entry must be submitted and approved before posting.');
                }
                if (($entry['source_type'] ?? '') !== 'approved_by' || empty($entry['source_id'])) {
                    throw new FinanceException('Entry requires approval before posting.');
                }
                $createdBy = (int) ($entry['created_by_user_id'] ?? 0);
                if ($createdBy === $postedBy) {
                    throw new AuthorizationException('Maker-checker: creator cannot post their own entry.');
                }
            }

            $this->assertBalanced($stokvelId, $journalEntryId);

            $this->db->exec(
                'UPDATE journal_entries
                 SET status = :status, posted_by_user_id = :posted_by, posted_at = :posted_at, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_POSTED,
                    'posted_by' => $postedBy,
                    'posted_at' => now_utc(),
                    'updated_at' => now_utc(),
                    'id' => $journalEntryId,
                    'stokvel_id' => $stokvelId,
                ]
            );
        });
    }

    public function reverse(int $stokvelId, int $journalEntryId, int $reversedBy, string $reason): int
    {
        return (int) $this->db->transaction(function () use ($stokvelId, $journalEntryId, $reversedBy, $reason) {
            $entry = $this->lockEntry($stokvelId, $journalEntryId);
            if ($entry['status'] !== self::STATUS_POSTED) {
                throw new FinanceException('Only posted entries can be reversed.');
            }

            $lines = $this->getLines($stokvelId, $journalEntryId);
            if ($lines === []) {
                throw new FinanceException('Cannot reverse an entry with no lines.');
            }

            $reversalId = $this->createDraft(
                $stokvelId,
                $reversedBy,
                (string) $entry['entry_date'],
                'REV-' . $entry['entry_number'],
                'Reversal: ' . $reason
            );

            foreach ($lines as $line) {
                $this->addLine(
                    $stokvelId,
                    $reversalId,
                    (int) $line['chart_of_account_id'],
                    (string) $line['credit'],
                    (string) $line['debit'],
                    isset($line['membership_id']) ? (int) $line['membership_id'] : null,
                    'Reversal of line #' . $line['id']
                );
            }

            $this->db->exec(
                'UPDATE journal_entries SET reversed_entry_id = :original_id, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'original_id' => $journalEntryId,
                    'updated_at' => now_utc(),
                    'id' => $reversalId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            $this->post($stokvelId, $reversalId, $reversedBy, true);

            $this->db->exec(
                'UPDATE journal_entries
                 SET status = :status, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'status' => self::STATUS_REVERSED,
                    'updated_at' => now_utc(),
                    'id' => $journalEntryId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            return $reversalId;
        });
    }

    public function markFailed(int $stokvelId, int $journalEntryId, string $reason): void
    {
        $entry = $this->getEntry($stokvelId, $journalEntryId);
        if ($entry === null) {
            throw new FinanceException('Journal entry not found.');
        }
        if ($entry['status'] === self::STATUS_POSTED) {
            throw new FinanceException('Posted entries cannot be marked failed.');
        }

        $this->db->exec(
            'UPDATE journal_entries
             SET status = :status, description = :description, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => self::STATUS_FAILED,
                'description' => 'Failed: ' . $reason,
                'updated_at' => now_utc(),
                'id' => $journalEntryId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    public function getEntry(int $stokvelId, int $journalEntryId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM journal_entries WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $journalEntryId, 'stokvel_id' => $stokvelId]
        );
    }

    public function getLines(int $stokvelId, int $journalEntryId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM journal_lines
             WHERE journal_entry_id = :journal_entry_id AND stokvel_id = :stokvel_id
             ORDER BY line_number ASC, id ASC',
            ['journal_entry_id' => $journalEntryId, 'stokvel_id' => $stokvelId]
        );
    }

    public function validateBalance(int $stokvelId, int $journalEntryId): bool
    {
        $totals = $this->calculateTotals($stokvelId, $journalEntryId);

        return MoneyMath::equals($totals['debit'], $totals['credit']) && MoneyMath::isPositive($totals['debit']);
    }

    private function assertBalanced(int $stokvelId, int $journalEntryId): void
    {
        if (!$this->validateBalance($stokvelId, $journalEntryId)) {
            throw new FinanceException('Journal entry is not balanced (debits must equal credits).');
        }
    }

    /** @return array{debit: string, credit: string} */
    private function calculateTotals(int $stokvelId, int $journalEntryId): array
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(debit), 0) AS total_debit, COALESCE(SUM(credit), 0) AS total_credit
             FROM journal_lines
             WHERE journal_entry_id = :journal_entry_id AND stokvel_id = :stokvel_id',
            ['journal_entry_id' => $journalEntryId, 'stokvel_id' => $stokvelId]
        );

        return [
            'debit' => MoneyMath::normalize($row['total_debit'] ?? '0'),
            'credit' => MoneyMath::normalize($row['total_credit'] ?? '0'),
        ];
    }

    private function requireEditableEntry(int $stokvelId, int $journalEntryId): array
    {
        $entry = $this->getEntry($stokvelId, $journalEntryId);
        if ($entry === null) {
            throw new FinanceException('Journal entry not found.');
        }
        if ($entry['status'] !== self::STATUS_DRAFT) {
            throw new FinanceException('Only draft entries can be modified.');
        }

        return $entry;
    }

    private function lockEntry(int $stokvelId, int $journalEntryId): array
    {
        $entry = $this->db->fetch(
            'SELECT * FROM journal_entries WHERE id = :id AND stokvel_id = :stokvel_id FOR UPDATE',
            ['id' => $journalEntryId, 'stokvel_id' => $stokvelId]
        );
        if ($entry === null) {
            throw new FinanceException('Journal entry not found.');
        }

        return $entry;
    }

    private function isMakerCheckerEnabled(int $stokvelId): bool
    {
        $row = $this->db->fetch(
            'SELECT require_dual_approval FROM stokvel_settings WHERE stokvel_id = :stokvel_id LIMIT 1',
            ['stokvel_id' => $stokvelId]
        );
        if ($row === null) {
            return false;
        }

        return (int) ($row['require_dual_approval'] ?? 0) === 1;
    }

    private function uniqueEntryNumber(int $stokvelId, string $reference): string
    {
        $base = preg_replace('/[^A-Za-z0-9\-]/', '', $reference) ?: 'JE';
        $base = substr($base, 0, 24);
        $candidate = $base;
        $i = 0;
        while ($this->db->fetch(
            'SELECT id FROM journal_entries WHERE stokvel_id = :sid AND entry_number = :n LIMIT 1',
            ['sid' => $stokvelId, 'n' => $candidate]
        )) {
            $i++;
            $candidate = substr($base, 0, 20) . '-' . $i;
        }

        return $candidate;
    }

    private function findByIdempotencyKey(int $stokvelId, string $key): ?array
    {
        $link = $this->db->fetch(
            'SELECT response_body_hash FROM idempotency_keys
             WHERE stokvel_id = :stokvel_id AND idempotency_key = :key
             LIMIT 1',
            ['stokvel_id' => $stokvelId, 'key' => $key]
        );
        if ($link === null || empty($link['response_body_hash'])) {
            return null;
        }

        return $this->getEntry($stokvelId, (int) $link['response_body_hash']);
    }

    private function storeIdempotencyKey(int $stokvelId, string $scope, string $key, string $resourceId): void
    {
        $now = now_utc();
        $this->db->insert(
            'INSERT INTO idempotency_keys
                (stokvel_id, idempotency_key, request_method, request_path, response_body_hash, expires_at, created_at)
             VALUES
                (:stokvel_id, :idempotency_key, :request_method, :request_path, :response_body_hash, :expires_at, :created_at)
             ON DUPLICATE KEY UPDATE response_body_hash = VALUES(response_body_hash)',
            [
                'stokvel_id' => $stokvelId,
                'idempotency_key' => $key,
                'request_method' => 'POST',
                'request_path' => '/' . $scope,
                'response_body_hash' => $resourceId,
                'expires_at' => (new \DateTimeImmutable($now))->modify('+7 days')->format('Y-m-d H:i:s'),
                'created_at' => $now,
            ]
        );
    }
}
