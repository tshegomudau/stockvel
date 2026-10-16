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

        $interestType = $amortizationMethod === LoanScheduleCalculator::METHOD_FLAT
            ? 'flat'
            : 'reducing';

        $now = now_utc();
        $code = $this->nextCode($stokvelId, $name);

        return (int) $this->db->insert(
            'INSERT INTO loan_products
                (stokvel_id, code, name, status, min_amount, max_amount, interest_rate_percent,
                 max_term_months, requires_guarantor, is_active, interest_type, penalty_rate_percent,
                 requires_guarantors, min_guarantors, terms_json, created_at, updated_at)
             VALUES
                (:stokvel_id, :code, :name, :status, :min_amount, :max_amount, :interest_rate_percent,
                 :max_term_months, :requires_guarantor, 1, :interest_type, :penalty_rate_percent,
                 :requires_guarantors, :min_guarantors, :terms_json, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'code' => $code,
                'name' => $name,
                'status' => 'active',
                'min_amount' => MoneyMath::normalize($minAmount),
                'max_amount' => MoneyMath::normalize($maxAmount),
                'interest_rate_percent' => MoneyMath::normalize($annualInterestRatePercent),
                'max_term_months' => $maxTermMonths,
                'requires_guarantor' => $requiresGuarantors ? 1 : 0,
                'interest_type' => $interestType,
                'penalty_rate_percent' => MoneyMath::normalize($penaltyRatePercent),
                'requires_guarantors' => $requiresGuarantors ? 1 : 0,
                'min_guarantors' => $minGuarantors,
                'terms_json' => json_encode([
                    'min_term_months' => $minTermMonths,
                    'amortization_method' => $amortizationMethod,
                    'instalment_fee' => MoneyMath::normalize($instalmentFee),
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function get(int $stokvelId, int $productId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM loan_products WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $productId, 'stokvel_id' => $stokvelId]
        );
        return $row !== null ? $this->hydrate($row) : null;
    }

    public function listActive(int $stokvelId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM loan_products WHERE stokvel_id = :stokvel_id AND is_active = 1 ORDER BY name ASC',
            ['stokvel_id' => $stokvelId]
        );
        return array_map(fn (array $row) => $this->hydrate($row), $rows);
    }

    public function update(int $stokvelId, int $productId, array $fields): void
    {
        $map = [
            'name' => 'name',
            'min_amount' => 'min_amount',
            'max_amount' => 'max_amount',
            'annual_interest_rate_percent' => 'interest_rate_percent',
            'interest_rate_percent' => 'interest_rate_percent',
            'max_term_months' => 'max_term_months',
            'penalty_rate_percent' => 'penalty_rate_percent',
            'requires_guarantors' => 'requires_guarantors',
            'min_guarantors' => 'min_guarantors',
            'is_active' => 'is_active',
        ];

        $data = [];
        foreach ($map as $input => $column) {
            if (!array_key_exists($input, $fields)) {
                continue;
            }
            $value = $fields[$input];
            if (in_array($column, ['min_amount', 'max_amount', 'interest_rate_percent', 'penalty_rate_percent'], true)) {
                $value = MoneyMath::normalize((string) $value);
            }
            if ($column === 'requires_guarantors') {
                $value = ((int) $value) ? 1 : 0;
                $data['requires_guarantor'] = $value;
            }
            $data[$column] = $value;
        }

        if (isset($fields['amortization_method'])) {
            $method = (string) $fields['amortization_method'];
            $this->assertMethod($method);
            $data['interest_type'] = $method === LoanScheduleCalculator::METHOD_FLAT ? 'flat' : 'reducing';
        }

        if ($data === []) {
            return;
        }
        $data['updated_at'] = now_utc();
        if (isset($data['is_active'])) {
            $data['status'] = ((int) $data['is_active'] === 1) ? 'active' : 'inactive';
        }

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

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): array
    {
        $terms = [];
        if (!empty($row['terms_json'])) {
            $decoded = json_decode((string) $row['terms_json'], true);
            if (is_array($decoded)) {
                $terms = $decoded;
            }
        }
        $row['annual_interest_rate_percent'] = $row['interest_rate_percent'] ?? '0.00';
        $row['min_term_months'] = (int) ($terms['min_term_months'] ?? 1);
        $row['amortization_method'] = $terms['amortization_method']
            ?? ((($row['interest_type'] ?? '') === 'flat')
                ? LoanScheduleCalculator::METHOD_FLAT
                : LoanScheduleCalculator::METHOD_REDUCING_BALANCE);
        $row['instalment_fee'] = $terms['instalment_fee'] ?? '0.00';
        return $row;
    }

    private function nextCode(int $stokvelId, string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $name) ?: 'LOAN', 0, 8));
        $candidate = $base;
        $i = 0;
        while ($this->db->fetch(
            'SELECT id FROM loan_products WHERE stokvel_id = :sid AND code = :code LIMIT 1',
            ['sid' => $stokvelId, 'code' => $candidate]
        )) {
            $i++;
            $candidate = substr($base, 0, 6) . $i;
        }
        return $candidate;
    }

    private function assertMethod(string $method): void
    {
        if (!in_array($method, [LoanScheduleCalculator::METHOD_FLAT, LoanScheduleCalculator::METHOD_REDUCING_BALANCE], true)) {
            throw new FinanceException('Invalid amortization method.');
        }
    }
}
