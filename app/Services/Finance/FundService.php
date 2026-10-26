<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

/**
 * Internal funds/pockets — accounting allocations, not bank accounts.
 * Balance is always the sum of fund_transactions (balance_after of latest row).
 */
final class FundService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** Ensure starter funds exist for a stokvel (idempotent). */
    public function ensureDefaults(int $stokvelId): void
    {
        $defaults = [
            ['code' => 'admin', 'name' => 'Administration', 'description' => 'Admin pocket for operational costs', 'is_system' => 1, 'order' => 1],
            ['code' => 'general', 'name' => 'General Savings', 'description' => 'General stokvel savings allocation', 'is_system' => 1, 'order' => 2],
            ['code' => 'emergency', 'name' => 'Emergency', 'description' => 'Emergency reserve', 'is_system' => 0, 'order' => 3],
        ];
        $now = now_utc();
        foreach ($defaults as $d) {
            $exists = $this->db->fetch(
                'SELECT id FROM stokvel_funds WHERE stokvel_id = :sid AND code = :code LIMIT 1',
                ['sid' => $stokvelId, 'code' => $d['code']]
            );
            if ($exists) {
                continue;
            }
            $this->db->insert(
                'INSERT INTO stokvel_funds
                    (stokvel_id, code, name, description, is_system, allow_negative, display_order, is_active, created_at, updated_at)
                 VALUES
                    (:sid, :code, :name, :desc, :sys, 0, :ord, 1, :ca, :ua)',
                [
                    'sid' => $stokvelId,
                    'code' => $d['code'],
                    'name' => $d['name'],
                    'desc' => $d['description'],
                    'sys' => $d['is_system'],
                    'ord' => $d['order'],
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
        }
    }

    public function balance(int $stokvelId, int $fundId): string
    {
        $row = $this->db->fetch(
            'SELECT balance_after FROM fund_transactions
             WHERE stokvel_id = :sid AND fund_id = :fid
             ORDER BY id DESC LIMIT 1',
            ['sid' => $stokvelId, 'fid' => $fundId]
        );
        return MoneyMath::normalize((string) ($row['balance_after'] ?? '0'));
    }

    /**
     * @param array{entry_date?:string,description:string,reference?:?string,category_name?:?string,expense_category_id?:?int,source_type?:?string,source_id?:?int,idempotency_key?:?string} $meta
     */
    public function credit(
        int $stokvelId,
        int $fundId,
        string $amount,
        int $userId,
        array $meta = []
    ): int {
        return $this->post($stokvelId, $fundId, 'credit', $amount, $userId, $meta);
    }

    /** @param array<string, mixed> $meta */
    public function debit(
        int $stokvelId,
        int $fundId,
        string $amount,
        int $userId,
        array $meta = []
    ): int {
        return $this->post($stokvelId, $fundId, 'debit', $amount, $userId, $meta);
    }

    /** @param array<string, mixed> $meta */
    private function post(
        int $stokvelId,
        int $fundId,
        string $direction,
        string $amount,
        int $userId,
        array $meta
    ): int {
        $fund = $this->db->fetch(
            'SELECT * FROM stokvel_funds WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $fundId, 'sid' => $stokvelId]
        );
        if (!$fund || !(int) ($fund['is_active'] ?? 0)) {
            throw new FinanceException('Fund not found or inactive.');
        }

        $normalized = MoneyMath::normalize($amount);
        if ((float) $normalized <= 0) {
            throw new FinanceException('Amount must be greater than zero.');
        }

        $key = isset($meta['idempotency_key']) ? trim((string) $meta['idempotency_key']) : '';
        if ($key !== '') {
            $existing = $this->db->fetch(
                'SELECT id FROM fund_transactions WHERE stokvel_id = :sid AND idempotency_key = :k LIMIT 1',
                ['sid' => $stokvelId, 'k' => $key]
            );
            if ($existing) {
                return (int) $existing['id'];
            }
        }

        return (int) $this->db->transaction(function () use ($stokvelId, $fundId, $fund, $direction, $normalized, $userId, $meta, $key) {
            $current = $this->balance($stokvelId, $fundId);
            if ($direction === 'credit') {
                $after = MoneyMath::add($current, $normalized);
            } else {
                $after = MoneyMath::sub($current, $normalized);
                $allowNeg = (int) ($fund['allow_negative'] ?? 0) === 1;
                if (!$allowNeg && (float) $after < -0.00001) {
                    throw new FinanceException(
                        'Insufficient fund balance. Recorded balance is R ' . number_format((float) $current, 2)
                        . '. Negative balances are not permitted for this fund.'
                    );
                }
            }

            return (int) $this->db->insert(
                'INSERT INTO fund_transactions
                    (stokvel_id, fund_id, entry_date, direction, amount, balance_after, description, reference,
                     category_name, expense_category_id, source_type, source_id, created_by_user_id, idempotency_key, created_at)
                 VALUES
                    (:sid, :fid, :ed, :dir, :amt, :bal, :desc, :ref, :cat, :ecid, :stype, :sid2, :uid, :ikey, :ca)',
                [
                    'sid' => $stokvelId,
                    'fid' => $fundId,
                    'ed' => (string) ($meta['entry_date'] ?? date('Y-m-d')),
                    'dir' => $direction,
                    'amt' => $normalized,
                    'bal' => $after,
                    'desc' => (string) ($meta['description'] ?? ($direction === 'credit' ? 'Fund credit' : 'Fund debit')),
                    'ref' => isset($meta['reference']) ? (string) $meta['reference'] : null,
                    'cat' => isset($meta['category_name']) ? (string) $meta['category_name'] : null,
                    'ecid' => isset($meta['expense_category_id']) ? (int) $meta['expense_category_id'] : null,
                    'stype' => isset($meta['source_type']) ? (string) $meta['source_type'] : null,
                    'sid2' => isset($meta['source_id']) ? (int) $meta['source_id'] : null,
                    'uid' => $userId,
                    'ikey' => $key !== '' ? $key : null,
                    'ca' => now_utc(),
                ]
            );
        });
    }

    /** @return array{collected:string,spent:string,balance:string} */
    public function summary(int $stokvelId, int $fundId): array
    {
        $row = $this->db->fetch(
            "SELECT
                COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount ELSE 0 END), 0) AS collected,
                COALESCE(SUM(CASE WHEN direction = 'debit' THEN amount ELSE 0 END), 0) AS spent
             FROM fund_transactions WHERE stokvel_id = :sid AND fund_id = :fid",
            ['sid' => $stokvelId, 'fid' => $fundId]
        );
        return [
            'collected' => MoneyMath::normalize((string) ($row['collected'] ?? '0')),
            'spent' => MoneyMath::normalize((string) ($row['spent'] ?? '0')),
            'balance' => $this->balance($stokvelId, $fundId),
        ];
    }

    public function findAdminFund(int $stokvelId): ?array
    {
        $this->ensureDefaults($stokvelId);
        return $this->db->fetch(
            "SELECT * FROM stokvel_funds WHERE stokvel_id = :sid AND code = 'admin' LIMIT 1",
            ['sid' => $stokvelId]
        );
    }
}
