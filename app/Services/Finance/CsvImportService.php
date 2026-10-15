<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

final class CsvImportService
{
    /** @var list<string> */
    private const REQUIRED_HEADERS = ['member_number', 'amount', 'payment_method', 'paid_at', 'reference'];

    public function __construct(
        private readonly ContributionService $contributions,
        private readonly \App\Core\Database $db
    ) {
    }

    /** @return array{valid: bool, headers: list<string>, rows: list<array<string, mixed>>, errors: list<string>, summary: array{total_rows: int, valid_rows: int, total_amount: string}} */
    public function preview(int $stokvelId, string $csvContent): array
    {
        $parsed = $this->parseCsv($csvContent);
        $errors = [];
        $validRows = [];
        $totalAmount = '0.00';

        $headerErrors = $this->validateHeaders($parsed['headers']);
        if ($headerErrors !== []) {
            return [
                'valid' => false,
                'headers' => $parsed['headers'],
                'rows' => [],
                'errors' => $headerErrors,
                'summary' => ['total_rows' => 0, 'valid_rows' => 0, 'total_amount' => '0.00'],
            ];
        }

        foreach ($parsed['rows'] as $index => $row) {
            $line = $index + 2;
            $rowResult = $this->validateRow($stokvelId, $row, $line);
            if ($rowResult['errors'] !== []) {
                array_push($errors, ...$rowResult['errors']);
                continue;
            }
            $validRows[] = $rowResult['normalized'];
            $totalAmount = MoneyMath::add($totalAmount, $rowResult['normalized']['amount']);
        }

        return [
            'valid' => $errors === [] && $validRows !== [],
            'headers' => $parsed['headers'],
            'rows' => $validRows,
            'errors' => $errors,
            'summary' => [
                'total_rows' => count($parsed['rows']),
                'valid_rows' => count($validRows),
                'total_amount' => $totalAmount,
            ],
        ];
    }

    /** @return array{imported: int, payments: list<array<string, mixed>>, errors: list<string>} */
    public function import(int $stokvelId, string $csvContent, int $recordedBy, ?string $batchIdempotencyKey = null): array
    {
        $preview = $this->preview($stokvelId, $csvContent);
        if (!$preview['valid']) {
            throw new FinanceException('CSV validation failed: ' . implode('; ', $preview['errors']));
        }

        $payments = [];
        foreach ($preview['rows'] as $index => $row) {
            $rowKey = $batchIdempotencyKey !== null
                ? $batchIdempotencyKey . ':' . $index
                : null;
            $payments[] = $this->contributions->capturePayment(
                $stokvelId,
                (int) $row['member_id'],
                (string) $row['amount'],
                (string) $row['payment_method'],
                $recordedBy,
                $rowKey,
                $row['reference'] ?? null,
                $row['paid_at'] ?? null
            );
        }

        if ($batchIdempotencyKey !== null && $batchIdempotencyKey !== '') {
            $this->db->insert(
                'INSERT INTO idempotency_keys (stokvel_id, scope, idempotency_key, resource_id, created_at, expires_at)
                 VALUES (:stokvel_id, :scope, :idempotency_key, :resource_id, :created_at, :expires_at)
                 ON DUPLICATE KEY UPDATE resource_id = resource_id',
                [
                    'stokvel_id' => $stokvelId,
                    'scope' => 'contribution_bulk',
                    'idempotency_key' => $batchIdempotencyKey,
                    'resource_id' => json_encode(array_column($payments, 'id'), JSON_THROW_ON_ERROR),
                    'created_at' => now_utc(),
                    'expires_at' => (new \DateTimeImmutable(now_utc()))->modify('+30 days')->format('Y-m-d H:i:s'),
                ]
            );
        }

        return [
            'imported' => count($payments),
            'payments' => $payments,
            'errors' => [],
        ];
    }

    /** @return array{headers: list<string>, rows: list<array<string, string>>} */
    private function parseCsv(string $csvContent): array
    {
        $csvContent = trim($csvContent);
        if ($csvContent === '') {
            throw new FinanceException('CSV content is empty.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $csvContent) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
        if ($lines === []) {
            throw new FinanceException('CSV content is empty.');
        }

        $headers = str_getcsv($lines[0]);
        $headers = array_map(static fn (string $h): string => strtolower(trim($h)), $headers);
        $rows = [];
        for ($i = 1, $c = count($lines); $i < $c; ++$i) {
            $values = str_getcsv($lines[$i]);
            if (count($values) === 1 && trim((string) $values[0]) === '') {
                continue;
            }
            $assoc = [];
            foreach ($headers as $idx => $header) {
                $assoc[$header] = trim((string) ($values[$idx] ?? ''));
            }
            $rows[] = $assoc;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @param list<string> $headers */
    private function validateHeaders(array $headers): array
    {
        $errors = [];
        foreach (self::REQUIRED_HEADERS as $required) {
            if (!in_array($required, $headers, true)) {
                $errors[] = "Missing required column: {$required}";
            }
        }

        return $errors;
    }

    /** @param array<string, string> $row */
    private function validateRow(int $stokvelId, array $row, int $line): array
    {
        $errors = [];
        $memberNumber = $row['member_number'] ?? '';
        if ($memberNumber === '') {
            $errors[] = "Line {$line}: member_number is required.";
        }

        $member = null;
        if ($memberNumber !== '') {
            $member = $this->db->fetch(
                'SELECT id, status FROM members WHERE stokvel_id = :stokvel_id AND member_number = :member_number LIMIT 1',
                ['stokvel_id' => $stokvelId, 'member_number' => $memberNumber]
            );
            if ($member === null) {
                $errors[] = "Line {$line}: member_number {$memberNumber} not found.";
            } elseif (($member['status'] ?? '') !== 'active') {
                $errors[] = "Line {$line}: member {$memberNumber} is not active.";
            }
        }

        $amountRaw = $row['amount'] ?? '';
        try {
            $amount = MoneyMath::normalize($amountRaw);
            if (!MoneyMath::isPositive($amount)) {
                $errors[] = "Line {$line}: amount must be positive.";
            }
        } catch (\InvalidArgumentException) {
            $errors[] = "Line {$line}: invalid amount.";
            $amount = '0.00';
        }

        $method = strtolower($row['payment_method'] ?? '');
        if (!in_array($method, ContributionService::PAYMENT_METHODS, true)) {
            $errors[] = "Line {$line}: invalid payment_method.";
        }

        $paidAt = $row['paid_at'] ?? '';
        if ($paidAt !== '' && !$this->isValidDate($paidAt)) {
            $errors[] = "Line {$line}: invalid paid_at date (expected Y-m-d or Y-m-d H:i:s).";
        }

        if ($errors !== [] || $member === null) {
            return ['errors' => $errors, 'normalized' => []];
        }

        return [
            'errors' => [],
            'normalized' => [
                'member_id' => (int) $member['id'],
                'member_number' => $memberNumber,
                'amount' => $amount,
                'payment_method' => $method,
                'paid_at' => $paidAt !== '' ? $this->normalizeDateTime($paidAt) : null,
                'reference' => $row['reference'] ?? null,
            ],
        ];
    }

    private function isValidDate(string $value): bool
    {
        $formats = ['Y-m-d', 'Y-m-d H:i:s'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $value);
            if ($dt !== false && $dt->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    private function normalizeDateTime(string $value): string
    {
        if (strlen($value) === 10) {
            return $value . ' 00:00:00';
        }

        return $value;
    }
}
