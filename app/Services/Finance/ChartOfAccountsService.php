<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Exceptions\FinanceException;

final class ChartOfAccountsService
{
    /** @var list<array{code: string, name: string, account_type: string, sub_type: string, is_system: int}> */
    private const DEFAULT_ACCOUNTS = [
        ['code' => '1000', 'name' => 'Cash on Hand', 'account_type' => 'asset', 'sub_type' => 'cash', 'is_system' => 1],
        ['code' => '1010', 'name' => 'Bank Account', 'account_type' => 'asset', 'sub_type' => 'bank', 'is_system' => 1],
        ['code' => '1100', 'name' => 'Member Receivables', 'account_type' => 'asset', 'sub_type' => 'receivable', 'is_system' => 1],
        ['code' => '1200', 'name' => 'Loan Receivables', 'account_type' => 'asset', 'sub_type' => 'loan_receivable', 'is_system' => 1],
        ['code' => '2000', 'name' => 'Member Credits (Overpayments)', 'account_type' => 'liability', 'sub_type' => 'member_credit', 'is_system' => 1],
        ['code' => '2100', 'name' => 'Accounts Payable', 'account_type' => 'liability', 'sub_type' => 'payable', 'is_system' => 1],
        ['code' => '2200', 'name' => 'Payouts Payable', 'account_type' => 'liability', 'sub_type' => 'payout_payable', 'is_system' => 1],
        ['code' => '3000', 'name' => 'Member Equity / Fund Balance', 'account_type' => 'equity', 'sub_type' => 'fund_balance', 'is_system' => 1],
        ['code' => '3100', 'name' => 'Retained Surplus', 'account_type' => 'equity', 'sub_type' => 'retained', 'is_system' => 1],
        ['code' => '4000', 'name' => 'Contribution Income', 'account_type' => 'income', 'sub_type' => 'contributions', 'is_system' => 1],
        ['code' => '4100', 'name' => 'Penalty Income', 'account_type' => 'income', 'sub_type' => 'penalties', 'is_system' => 1],
        ['code' => '4200', 'name' => 'Interest Income', 'account_type' => 'income', 'sub_type' => 'interest', 'is_system' => 1],
        ['code' => '4300', 'name' => 'Other Income', 'account_type' => 'income', 'sub_type' => 'other_income', 'is_system' => 1],
        ['code' => '5000', 'name' => 'Administrative Expenses', 'account_type' => 'expense', 'sub_type' => 'admin', 'is_system' => 1],
        ['code' => '5100', 'name' => 'Meeting Expenses', 'account_type' => 'expense', 'sub_type' => 'meeting', 'is_system' => 1],
        ['code' => '5200', 'name' => 'Bank Charges', 'account_type' => 'expense', 'sub_type' => 'bank_charges', 'is_system' => 1],
        ['code' => '5300', 'name' => 'Loan Loss / Write-off', 'account_type' => 'expense', 'sub_type' => 'loan_loss', 'is_system' => 1],
        ['code' => '5400', 'name' => 'Payout Expenses', 'account_type' => 'expense', 'sub_type' => 'payout', 'is_system' => 1],
        ['code' => '5500', 'name' => 'Other Expenses', 'account_type' => 'expense', 'sub_type' => 'other_expense', 'is_system' => 1],
    ];

    public function __construct(
        private readonly Database $db
    ) {
    }

    public function seedDefault(int $stokvelId): int
    {
        return $this->ensureDefaultAccounts($stokvelId);
    }

    /**
     * Upsert the full system chart so finance posting never fails on missing sub_types.
     */
    public function ensureDefaultAccounts(int $stokvelId): int
    {
        $now = now_utc();
        $touched = 0;
        foreach (self::DEFAULT_ACCOUNTS as $account) {
            $existing = $this->db->fetch(
                'SELECT id FROM chart_of_accounts WHERE stokvel_id = :stokvel_id AND code = :code LIMIT 1',
                ['stokvel_id' => $stokvelId, 'code' => $account['code']]
            );
            if ($existing) {
                $this->db->exec(
                    'UPDATE chart_of_accounts
                     SET name = :name, account_type = :account_type, sub_type = :sub_type,
                         is_system = :is_system, is_active = 1, updated_at = :updated_at
                     WHERE id = :id AND stokvel_id = :stokvel_id',
                    [
                        'name' => $account['name'],
                        'account_type' => $account['account_type'],
                        'sub_type' => $account['sub_type'],
                        'is_system' => $account['is_system'],
                        'updated_at' => $now,
                        'id' => (int) $existing['id'],
                        'stokvel_id' => $stokvelId,
                    ]
                );
            } else {
                $this->db->insert(
                    'INSERT INTO chart_of_accounts
                        (stokvel_id, code, name, account_type, sub_type, parent_id, is_system, is_active, created_at, updated_at)
                     VALUES
                        (:stokvel_id, :code, :name, :account_type, :sub_type, NULL, :is_system, 1, :created_at, :updated_at)',
                    [
                        'stokvel_id' => $stokvelId,
                        'code' => $account['code'],
                        'name' => $account['name'],
                        'account_type' => $account['account_type'],
                        'sub_type' => $account['sub_type'],
                        'is_system' => $account['is_system'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
            ++$touched;
        }

        return $touched;
    }

    /** @return list<array{code: string, name: string, account_type: string, sub_type: string, is_system: int}> */
    public static function defaultAccounts(): array
    {
        return self::DEFAULT_ACCOUNTS;
    }

    public function findByCode(int $stokvelId, string $code): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM chart_of_accounts WHERE stokvel_id = :stokvel_id AND code = :code AND is_active = 1 LIMIT 1',
            ['stokvel_id' => $stokvelId, 'code' => $code]
        );
    }

    public function findBySubType(int $stokvelId, string $subType): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM chart_of_accounts
             WHERE stokvel_id = :stokvel_id AND sub_type = :sub_type AND is_active = 1
             ORDER BY id ASC LIMIT 1',
            ['stokvel_id' => $stokvelId, 'sub_type' => $subType]
        );
    }

    public function requireBySubType(int $stokvelId, string $subType): array
    {
        $account = $this->findBySubType($stokvelId, $subType);
        if ($account === null) {
            $this->ensureDefaultAccounts($stokvelId);
            $account = $this->findBySubType($stokvelId, $subType);
        }
        if ($account === null) {
            throw new FinanceException("Chart of accounts entry missing for sub_type: {$subType}");
        }

        return $account;
    }

    public function listActive(int $stokvelId, ?string $accountType = null): array
    {
        $sql = 'SELECT * FROM chart_of_accounts WHERE stokvel_id = :stokvel_id AND is_active = 1';
        $params = ['stokvel_id' => $stokvelId];
        if ($accountType !== null) {
            $sql .= ' AND account_type = :account_type';
            $params['account_type'] = $accountType;
        }
        $sql .= ' ORDER BY code ASC';

        return $this->db->fetchAll($sql, $params);
    }

    public function createCustom(
        int $stokvelId,
        string $code,
        string $name,
        string $accountType,
        string $subType = 'custom',
        ?int $parentId = null
    ): int {
        if ($this->findByCode($stokvelId, $code) !== null) {
            throw new FinanceException('Account code already exists.');
        }

        $now = now_utc();
        return (int) $this->db->insert(
            'INSERT INTO chart_of_accounts
                (stokvel_id, code, name, account_type, sub_type, parent_id, is_system, is_active, created_at, updated_at)
             VALUES
                (:stokvel_id, :code, :name, :account_type, :sub_type, :parent_id, 0, 1, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'code' => $code,
                'name' => $name,
                'account_type' => $accountType,
                'sub_type' => $subType,
                'parent_id' => $parentId,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }
}
