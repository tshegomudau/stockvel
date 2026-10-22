<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

final class CsvImportService
{
    /** Canonical headers (also accepted as friendly aliases). */
    private const REQUIRED_HEADERS = ['member_number', 'amount', 'payment_method', 'paid_at'];

    /** @var array<string, string> alias => canonical */
    private const HEADER_ALIASES = [
        'member_number' => 'member_number',
        'member number' => 'member_number',
        'member_number_or_email' => 'member_number',
        'member number or email' => 'member_number',
        'email' => 'member_number',
        'amount' => 'amount',
        'payment_method' => 'payment_method',
        'payment method' => 'payment_method',
        'method' => 'payment_method',
        'paid_at' => 'paid_at',
        'payment date' => 'paid_at',
        'date' => 'paid_at',
        'reference' => 'reference',
        'ref' => 'reference',
    ];

    public function __construct(
        private readonly ContributionService $contributions,
        private readonly \App\Core\Database $db
    ) {
    }

    public function templateCsv(): string
    {
        $today = date('Y-m-d');
        return "Member Number or Email,Amount,Payment Method,Payment Date,Reference\n"
            . "UB004,500.00,eft,{$today},Monthly contribution\n"
            . "lerato.pillay@example.co.za,500.00,cash,{$today},\n";
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
            'valid' => $validRows !== [],
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

    /** @return array{imported: int, payments: list<array<string, mixed>>, errors: list<string>, skipped: int} */
    public function import(int $stokvelId, string $csvContent, int $recordedBy, ?string $batchIdempotencyKey = null): array
    {
        $preview = $this->preview($stokvelId, $csvContent);
        if ($preview['rows'] === []) {
            throw new FinanceException(
                $preview['errors'] !== []
                    ? 'CSV validation failed: ' . implode(' ', array_slice($preview['errors'], 0, 5))
                    : 'No valid rows found in the CSV file.'
            );
        }

        app()->container()->get(ChartOfAccountsService::class)->ensureDefaultAccounts($stokvelId);

        $payments = [];
        $importErrors = $preview['errors'];
        foreach ($preview['rows'] as $index => $row) {
            $rowKey = $batchIdempotencyKey !== null
                ? $batchIdempotencyKey . ':' . $index
                : 'csv-' . hash('sha256', $stokvelId . ':' . $row['member_id'] . ':' . $row['amount'] . ':' . ($row['paid_at'] ?? '') . ':' . $index);
            try {
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
            } catch (FinanceException $e) {
                $importErrors[] = 'Row ' . ($index + 2) . ': ' . $e->getMessage();
            }
        }

        return [
            'imported' => count($payments),
            'payments' => $payments,
            'errors' => $importErrors,
            'skipped' => count($importErrors),
        ];
    }

    /** @return array{headers: list<string>, rows: list<array<string, string>>} */
    private function parseCsv(string $csvContent): array
    {
        // Strip UTF-8 BOM
        if (str_starts_with($csvContent, "\xEF\xBB\xBF")) {
            $csvContent = substr($csvContent, 3);
        }
        $csvContent = trim($csvContent);
        if ($csvContent === '') {
            throw new FinanceException('CSV content is empty.');
        }

        $lines = preg_split('/\r\n|\r|\n/', $csvContent) ?: [];
        $lines = array_values(array_filter($lines, static fn (string $line): bool => trim($line) !== ''));
        if ($lines === []) {
            throw new FinanceException('CSV content is empty.');
        }

        $rawHeaders = str_getcsv($lines[0]);
        $headers = [];
        foreach ($rawHeaders as $h) {
            $key = strtolower(trim((string) $h));
            $headers[] = self::HEADER_ALIASES[$key] ?? $key;
        }

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
                $label = match ($required) {
                    'member_number' => 'Member Number or Email',
                    'payment_method' => 'Payment Method',
                    'paid_at' => 'Payment Date',
                    default => ucfirst(str_replace('_', ' ', $required)),
                };
                $errors[] = "Missing required column: {$label}";
            }
        }

        return $errors;
    }

    /** @param array<string, string> $row */
    private function validateRow(int $stokvelId, array $row, int $line): array
    {
        $errors = [];
        $memberRef = trim((string) ($row['member_number'] ?? ''));
        if ($memberRef === '') {
            $errors[] = "Row {$line}: Member number or email is required.";
        }

        $member = null;
        if ($memberRef !== '') {
            $member = $this->db->fetch(
                'SELECT sm.id, sm.status, sm.member_number FROM memberships sm
                 INNER JOIN users u ON u.id = sm.user_id
                 WHERE sm.stokvel_id = :stokvel_id
                   AND (sm.member_number = :ref_number OR u.email = :ref_email)
                 LIMIT 1',
                [
                    'stokvel_id' => $stokvelId,
                    'ref_number' => $memberRef,
                    'ref_email' => $memberRef,
                ]
            );
            if ($member === null) {
                $errors[] = "Row {$line}: Member \"{$memberRef}\" was not found.";
            } elseif (($member['status'] ?? '') !== 'active') {
                $errors[] = "Row {$line}: Member \"{$memberRef}\" is not active.";
            }
        }

        $amountRaw = $row['amount'] ?? '';
        try {
            $amount = MoneyMath::normalize($amountRaw);
            if (!MoneyMath::isPositive($amount)) {
                $errors[] = "Row {$line}: Amount must be greater than zero.";
            }
        } catch (\InvalidArgumentException) {
            $errors[] = "Row {$line}: Amount is not a valid number.";
            $amount = '0.00';
        }

        $method = strtolower(trim((string) ($row['payment_method'] ?? 'eft')));
        if ($method === '') {
            $method = 'eft';
        }
        if (!in_array($method, ContributionService::PAYMENT_METHODS, true)) {
            $errors[] = "Row {$line}: Payment method \"{$method}\" is invalid. Use: "
                . implode(', ', ContributionService::PAYMENT_METHODS) . '.';
        }

        $paidAt = trim((string) ($row['paid_at'] ?? ''));
        if ($paidAt === '') {
            $errors[] = "Row {$line}: Payment date is required (YYYY-MM-DD).";
        } elseif (!$this->isValidDate($paidAt)) {
            $errors[] = "Row {$line}: Payment date \"{$paidAt}\" is invalid (use YYYY-MM-DD).";
        }

        if ($errors !== [] || $member === null) {
            return ['errors' => $errors, 'normalized' => []];
        }

        return [
            'errors' => [],
            'normalized' => [
                'member_id' => (int) $member['id'],
                'member_number' => (string) ($member['member_number'] ?? $memberRef),
                'amount' => $amount,
                'payment_method' => $method,
                'paid_at' => $this->normalizeDateTime($paidAt),
                'reference' => ($row['reference'] ?? '') !== '' ? (string) $row['reference'] : null,
            ],
        ];
    }

    private function isValidDate(string $value): bool
    {
        foreach (['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y'] as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $value);
            if ($dt !== false && $dt->format($format) === $value) {
                return true;
            }
        }

        return false;
    }

    private function normalizeDateTime(string $value): string
    {
        foreach (['Y-m-d', 'Y-m-d H:i:s', 'd/m/Y', 'd-m-Y'] as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $value);
            if ($dt !== false && $dt->format($format) === $value) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        return strlen($value) === 10 ? $value . ' 00:00:00' : $value;
    }
}
