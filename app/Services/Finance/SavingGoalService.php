<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

final class SavingGoalService
{
    public function __construct(
        private readonly Database $db,
        private readonly FundService $funds
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(int $stokvelId, ?string $status = null): array
    {
        $sql = 'SELECT g.*, f.name AS fund_name, f.code AS fund_code
                FROM saving_goals g
                LEFT JOIN stokvel_funds f ON f.id = g.fund_id AND f.stokvel_id = g.stokvel_id
                WHERE g.stokvel_id = :sid';
        $params = ['sid' => $stokvelId];
        if ($status !== null && $status !== '') {
            $sql .= ' AND g.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY FIELD(g.status, \'active\', \'achieved\', \'cancelled\'), g.target_date IS NULL, g.target_date ASC, g.id DESC';

        $rows = $this->db->fetchAll($sql, $params);
        foreach ($rows as &$row) {
            $row = $this->withProgress($stokvelId, $row);
        }
        unset($row);
        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function find(int $stokvelId, int $goalId): ?array
    {
        $row = $this->db->fetch(
            'SELECT g.*, f.name AS fund_name, f.code AS fund_code
             FROM saving_goals g
             LEFT JOIN stokvel_funds f ON f.id = g.fund_id AND f.stokvel_id = g.stokvel_id
             WHERE g.id = :id AND g.stokvel_id = :sid LIMIT 1',
            ['id' => $goalId, 'sid' => $stokvelId]
        );
        return $row ? $this->withProgress($stokvelId, $row) : null;
    }

    /**
     * @param array{name:string,description?:?string,target_amount:string,target_date?:?string,fund_id?:?int,progress_amount?:string} $data
     */
    public function create(int $stokvelId, int $userId, array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new FinanceException('Goal name is required.');
        }
        $target = MoneyMath::normalize((string) ($data['target_amount'] ?? '0'));
        if (!MoneyMath::isPositive($target)) {
            throw new FinanceException('Target amount must be greater than zero.');
        }

        $fundId = isset($data['fund_id']) && (int) $data['fund_id'] > 0 ? (int) $data['fund_id'] : null;
        if ($fundId !== null) {
            $this->assertFund($stokvelId, $fundId);
        }

        $progress = MoneyMath::normalize((string) ($data['progress_amount'] ?? '0'));
        $now = now_utc();
        $desc = trim((string) ($data['description'] ?? ''));
        $targetDate = trim((string) ($data['target_date'] ?? ''));

        return (int) $this->db->insert(
            'INSERT INTO saving_goals
                (stokvel_id, fund_id, name, description, target_amount, target_date, progress_amount,
                 status, created_by, created_at, updated_at)
             VALUES
                (:sid, :fid, :name, :desc, :target, :tdate, :progress, :status, :by, :ca, :ua)',
            [
                'sid' => $stokvelId,
                'fid' => $fundId,
                'name' => $name,
                'desc' => $desc !== '' ? $desc : null,
                'target' => $target,
                'tdate' => $targetDate !== '' ? $targetDate : null,
                'progress' => $progress,
                'status' => 'active',
                'by' => $userId,
                'ca' => $now,
                'ua' => $now,
            ]
        );
    }

    /**
     * @param array{name?:string,description?:?string,target_amount?:string,target_date?:?string,fund_id?:?int,progress_amount?:string,status?:string} $data
     */
    public function update(int $stokvelId, int $goalId, array $data): void
    {
        $existing = $this->find($stokvelId, $goalId);
        if ($existing === null) {
            throw new FinanceException('Saving goal not found.');
        }

        $name = array_key_exists('name', $data) ? trim((string) $data['name']) : (string) $existing['name'];
        if ($name === '') {
            throw new FinanceException('Goal name is required.');
        }

        $target = array_key_exists('target_amount', $data)
            ? MoneyMath::normalize((string) $data['target_amount'])
            : MoneyMath::normalize((string) $existing['target_amount']);
        if (!MoneyMath::isPositive($target)) {
            throw new FinanceException('Target amount must be greater than zero.');
        }

        $fundId = $existing['fund_id'] !== null ? (int) $existing['fund_id'] : null;
        if (array_key_exists('fund_id', $data)) {
            $fundId = $data['fund_id'] !== null && (int) $data['fund_id'] > 0 ? (int) $data['fund_id'] : null;
            if ($fundId !== null) {
                $this->assertFund($stokvelId, $fundId);
            }
        }

        $progress = array_key_exists('progress_amount', $data)
            ? MoneyMath::normalize((string) $data['progress_amount'])
            : MoneyMath::normalize((string) ($existing['progress_amount'] ?? '0'));

        $status = array_key_exists('status', $data)
            ? (string) $data['status']
            : (string) $existing['status'];
        if (!in_array($status, ['active', 'achieved', 'cancelled'], true)) {
            throw new FinanceException('Invalid goal status.');
        }

        $desc = array_key_exists('description', $data)
            ? trim((string) ($data['description'] ?? ''))
            : (string) ($existing['description'] ?? '');
        $targetDate = array_key_exists('target_date', $data)
            ? trim((string) ($data['target_date'] ?? ''))
            : (string) ($existing['target_date'] ?? '');

        $this->db->exec(
            'UPDATE saving_goals
             SET fund_id = :fid, name = :name, description = :desc, target_amount = :target,
                 target_date = :tdate, progress_amount = :progress, status = :status, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid',
            [
                'fid' => $fundId,
                'name' => $name,
                'desc' => $desc !== '' ? $desc : null,
                'target' => $target,
                'tdate' => $targetDate !== '' ? $targetDate : null,
                'progress' => $progress,
                'status' => $status,
                'ua' => now_utc(),
                'id' => $goalId,
                'sid' => $stokvelId,
            ]
        );

        $this->autoAchieveIfMet($stokvelId, $goalId);
    }

    public function cancel(int $stokvelId, int $goalId): void
    {
        $this->update($stokvelId, $goalId, ['status' => 'cancelled']);
    }

    public function markAchieved(int $stokvelId, int $goalId): void
    {
        $this->update($stokvelId, $goalId, ['status' => 'achieved']);
    }

    /**
     * Progress prefers linked fund balance; otherwise uses manually recorded progress_amount.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function withProgress(int $stokvelId, array $row): array
    {
        $target = MoneyMath::normalize((string) ($row['target_amount'] ?? '0'));
        $source = 'manual';
        $current = MoneyMath::normalize((string) ($row['progress_amount'] ?? '0'));

        $fundId = isset($row['fund_id']) && (int) $row['fund_id'] > 0 ? (int) $row['fund_id'] : null;
        if ($fundId !== null) {
            $current = $this->funds->balance($stokvelId, $fundId);
            $source = 'fund';
        }

        $pct = 0;
        if (MoneyMath::isPositive($target)) {
            $pct = (int) max(0, min(100, (int) round((float) MoneyMath::div(MoneyMath::mul($current, '100'), $target, 4))));
        }

        $row['current_progress'] = $current;
        $row['progress_source'] = $source;
        $row['progress_percent'] = $pct;
        $row['remaining'] = MoneyMath::comp($target, $current) > 0 ? MoneyMath::sub($target, $current) : '0.00';
        return $row;
    }

    private function autoAchieveIfMet(int $stokvelId, int $goalId): void
    {
        $row = $this->find($stokvelId, $goalId);
        if ($row === null || ($row['status'] ?? '') !== 'active') {
            return;
        }
        if (MoneyMath::comp((string) $row['current_progress'], (string) $row['target_amount']) >= 0) {
            $this->db->exec(
                "UPDATE saving_goals SET status = 'achieved', updated_at = :ua WHERE id = :id AND stokvel_id = :sid AND status = 'active'",
                ['ua' => now_utc(), 'id' => $goalId, 'sid' => $stokvelId]
            );
        }
    }

    private function assertFund(int $stokvelId, int $fundId): void
    {
        $fund = $this->db->fetch(
            'SELECT id FROM stokvel_funds WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $fundId, 'sid' => $stokvelId]
        );
        if ($fund === null) {
            throw new FinanceException('Linked fund not found for this stokvel.');
        }
    }
}
