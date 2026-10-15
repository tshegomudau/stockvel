<?php

declare(strict_types=1);

namespace App\Services\Loans;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

final class LoanProductService
{
    public function __construct(
        private readonly Database $db
    ) {
    }

    public function create(
        int $stokvelId,
        string $name,
        string $minAmount,
        string $maxAmount,
        string $annualInterestRatePercent,
        int $minTermMonths,
        int $maxTermMonths,
        string $amortizationMethod,
        string $instalmentFee = '0.00',
        string $penaltyRatePercent = '0.00',
        bool $requiresGuarantors = false,
        int $minGuarantors = 0
    ): int {
        $this->assertMethod($amortizationMethod);
        if ($minTermMonths <= 0 || $maxTermMonths < $minTermMonths) {
            throw new FinanceException('Invalid loan term range.');
        }
        if (MoneyMath::comp($minAmount, $maxAmount) > 0) {
            throw new FinanceException('Minimum amount cannot exceed maximum amount.');
        }

        $now = now_utc();
        return (int) $this->db->insert(
            'INSERT INTO loan_products
                (stokvel_id, name, min_amount, max_amount, annual_interest_rate_percent,
                 min_term_months, max_term_months, amortization_method, instalment_fee, penalty_rate_percent,
                 requires_guarantors, min_guarantors, is_active, created_at, updated_at)
             VALUES
                (:stokvel_id, :name, :min_amount, :max_amount, :annual_interest_rate_percent,
                 :min_term_months, :max_term_months, :amortization_method, :instalment_fee, :penalty_rate_percent,
                 :requires_guarantors, :min_guarantors, 1, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'name' => $name,
                'min_amount' => MoneyMath::normalize($minAmount),
                'max_amount' => MoneyMath::normalize($maxAmount),
                'annual_interest_rate_percent' => MoneyMath::normalize($annualInterestRatePercent),
                'min_term_months' => $minTermMonths,
                'max_term_months' => $maxTermMonths,
                'amortization_method' => $amortizationMethod,
                'instalment_fee' => MoneyMath::normalize($instalmentFee),
                'penalty_rate_percent' => MoneyMath::normalize($penaltyRatePercent),
                'requires_guarantors' => $requiresGuarantors ? 1 : 0,
                'min_guarantors' => $minGuarantors,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function get(int $stokvelId, int $productId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM loan_products WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $productId, 'stokvel_id' => $stokvelId]
        );
    }

    public function listActive(int $stokvelId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM loan_products WHERE stokvel_id = :stokvel_id AND is_active = 1 ORDER BY name ASC',
            ['stokvel_id' => $stokvelId]
        );
    }

    public function update(int $stokvelId, int $productId, array $fields): void
    {
        $allowed = [
            'name', 'min_amount', 'max_amount', 'annual_interest_rate_percent',
            'min_term_months', 'max_term_months', 'amortization_method', 'instalment_fee',
            'penalty_rate_percent', 'requires_guarantors', 'min_guarantors', 'is_active',
        ];
        $data = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $fields)) {
                continue;
            }
            $value = $fields[$key];
            if (in_array($key, ['min_amount', 'max_amount', 'annual_interest_rate_percent', 'instalment_fee', 'penalty_rate_percent'], true)) {
                $value = MoneyMath::normalize((string) $value);
            }
            if ($key === 'amortization_method') {
                $this->assertMethod((string) $value);
            }
            $data[$key] = $value;
        }
        if ($data === []) {
            return;
        }
        $data['updated_at'] = now_utc();
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "{$col} = :{$col}";
        }
        $params = array_merge($data, ['id' => $productId, 'stokvel_id' => $stokvelId]);
        $affected = $this->db->exec(
            'UPDATE loan_products SET ' . implode(', ', $sets) . ' WHERE id = :id AND stokvel_id = :stokvel_id',
            $params
        );
        if ($affected === 0) {
            throw new FinanceException('Loan product not found.');
        }
    }

    private function assertMethod(string $method): void
    {
        if (!in_array($method, [LoanScheduleCalculator::METHOD_FLAT, LoanScheduleCalculator::METHOD_REDUCING_BALANCE], true)) {
            throw new FinanceException('Invalid amortization method.');
        }
    }
}
