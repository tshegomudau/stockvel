<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

final class ContributionService
{
    public const PAYMENT_METHODS = [
        'cash',
        'eft',
        'bank_deposit',
        'debit_order',
        'card',
        'other',
    ];

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REVERSED = 'reversed';

    public function __construct(
        private readonly Database $db,
        private readonly ChargeService $charges,
        private readonly LedgerService $ledger,
        private readonly ChartOfAccountsService $chartOfAccounts
    ) {
    }

    public function capturePayment(
        int $stokvelId,
        int $memberId,
        string $amount,
        string $paymentMethod,
        int $recordedBy,
        ?string $idempotencyKey = null,
        ?string $externalReference = null,
        ?string $paidAt = null
    ): array {
        $this->assertPaymentMethod($paymentMethod);
        $amount = MoneyMath::normalize($amount);
        if (!MoneyMath::isPositive($amount)) {
            throw new FinanceException('Payment amount must be positive.');
        }

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = $this->findByIdempotencyKey($stokvelId, $idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
        }

        return $this->db->transaction(function () use (
            $stokvelId,
            $memberId,
            $amount,
            $paymentMethod,
            $recordedBy,
            $idempotencyKey,
            $externalReference,
            $paidAt
        ): array {
            $member = $this->db->fetch(
                'SELECT id FROM memberships WHERE id = :id AND stokvel_id = :stokvel_id',
                ['id' => $memberId, 'stokvel_id' => $stokvelId]
            );
            if ($member === null) {
                throw new FinanceException('Member not found.');
            }

            $receiptNumber = $this->nextReceiptNumber($stokvelId);
            $now = now_utc();
            $paidAt = $this->normalizePaidAt($paidAt ?? $now);

            $paymentId = (int) $this->db->insert(
                'INSERT INTO payment_transactions
                    (stokvel_id, membership_id, transaction_ref, amount, payment_method,
                     external_reference, status, paid_at, recorded_by_user_id, notes,
                     idempotency_key, created_at, updated_at)
                 VALUES
                    (:stokvel_id, :membership_id, :transaction_ref, :amount, :payment_method,
                     :external_reference, :status, :paid_at, :recorded_by_user_id, :notes,
                     :idempotency_key, :created_at, :updated_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'membership_id' => $memberId,
                    'transaction_ref' => $receiptNumber,
                    'amount' => $amount,
                    'payment_method' => $paymentMethod,
                    'external_reference' => $externalReference,
                    'status' => self::STATUS_COMPLETED,
                    'paid_at' => $paidAt,
                    'recorded_by_user_id' => $recordedBy,
                    'notes' => null,
                    'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $allocation = $this->allocatePaymentToCharges($stokvelId, $paymentId, $memberId, $amount);

            $journalId = $this->postPaymentLedger(
                $stokvelId,
                $paymentId,
                $memberId,
                $amount,
                $recordedBy,
                $receiptNumber
            );

            $this->updatePaymentMeta($stokvelId, $paymentId, $allocation, $journalId);

            $payment = $this->getPayment($stokvelId, $paymentId);
            $payment['allocations'] = $allocation['items'];

            return $payment;
        });
    }

    /** @param list<array{member_id: int, amount: string, payment_method?: string, reference?: string}> $rows */
    public function captureBulk(
        int $stokvelId,
        array $rows,
        int $recordedBy,
        string $defaultPaymentMethod = 'eft',
        ?string $batchIdempotencyKey = null
    ): array {
        if ($batchIdempotencyKey !== null && $batchIdempotencyKey !== '') {
            $existing = $this->findBulkByIdempotencyKey($stokvelId, $batchIdempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
        }

        $results = [];
        foreach ($rows as $index => $row) {
            $memberId = (int) $row['member_id'];
            $amount = (string) $row['amount'];
            $method = (string) ($row['payment_method'] ?? $defaultPaymentMethod);
            $rowKey = $batchIdempotencyKey !== null
                ? $batchIdempotencyKey . ':' . $index
                : null;
            $results[] = $this->capturePayment(
                $stokvelId,
                $memberId,
                $amount,
                $method,
                $recordedBy,
                $rowKey,
                $row['reference'] ?? null
            );
        }

        if ($batchIdempotencyKey !== null && $batchIdempotencyKey !== '') {
            $this->storeBulkIdempotencyKey($stokvelId, $batchIdempotencyKey, array_column($results, 'id'));
        }

        return $results;
    }

    public function getPayment(int $stokvelId, int $paymentId): ?array
    {
        $row = $this->db->fetch(
            'SELECT * FROM payment_transactions WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $paymentId, 'stokvel_id' => $stokvelId]
        );
        if ($row === null) {
            return null;
        }

        return $this->enrichPayment($stokvelId, $row);
    }

    public function getMemberCreditBalance(int $stokvelId, int $memberId): string
    {
        $rows = $this->db->fetchAll(
            'SELECT pt.id, pt.amount, pt.notes
             FROM payment_transactions pt
             WHERE pt.stokvel_id = :stokvel_id AND pt.membership_id = :membership_id AND pt.status = :status',
            [
                'stokvel_id' => $stokvelId,
                'membership_id' => $memberId,
                'status' => self::STATUS_COMPLETED,
            ]
        );

        $total = '0.00';
        foreach ($rows as $row) {
            $meta = $this->parsePaymentNotes($row['notes'] ?? null);
            if (isset($meta['credit_amount'])) {
                $total = MoneyMath::add($total, MoneyMath::normalize((string) $meta['credit_amount']));
                continue;
            }
            $allocated = $this->sumAllocations($stokvelId, (int) $row['id']);
            $credit = MoneyMath::sub(MoneyMath::normalize((string) $row['amount']), $allocated);
            if (MoneyMath::isPositive($credit)) {
                $total = MoneyMath::add($total, $credit);
            }
        }

        return MoneyMath::normalize($total);
    }

    /** @return list<array{charge_id: int, amount: string}> */
    private function allocatePaymentToCharges(int $stokvelId, int $paymentId, int $memberId, string $amount): array
    {
        $remaining = MoneyMath::normalize($amount);
        $charges = $this->charges->getOutstandingCharges($stokvelId, $memberId);
        $allocations = [];
        $allocatedTotal = '0.00';

        foreach ($charges as $charge) {
            if (!MoneyMath::isPositive($remaining)) {
                break;
            }
            $balance = $this->charges->chargeBalance($charge);
            if (!MoneyMath::isPositive($balance)) {
                continue;
            }

            $apply = MoneyMath::min($remaining, $balance);
            $this->charges->applyAllocation($stokvelId, (int) $charge['id'], $apply);

            $this->db->insert(
                'INSERT INTO payment_allocations
                    (stokvel_id, payment_transaction_id, member_charge_id, amount, created_at)
                 VALUES
                    (:stokvel_id, :payment_transaction_id, :member_charge_id, :amount, :created_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'payment_transaction_id' => $paymentId,
                    'member_charge_id' => $charge['id'],
                    'amount' => $apply,
                    'created_at' => now_utc(),
                ]
            );

            $allocations[] = ['charge_id' => (int) $charge['id'], 'amount' => $apply];
            $remaining = MoneyMath::sub($remaining, $apply);
            $allocatedTotal = MoneyMath::add($allocatedTotal, $apply);
        }

        $credit = MoneyMath::normalize($remaining);

        return [
            'items' => $allocations,
            'allocated_amount' => $allocatedTotal,
            'credit_amount' => $credit,
        ];
    }

    /** @param array{items: list<array{charge_id: int, amount: string}>, allocated_amount: string, credit_amount: string} $allocation */
    private function updatePaymentMeta(int $stokvelId, int $paymentId, array $allocation, int $journalId): void
    {
        $notes = json_encode([
            'allocated_amount' => $allocation['allocated_amount'],
            'credit_amount' => $allocation['credit_amount'],
            'journal_entry_id' => $journalId,
        ], JSON_THROW_ON_ERROR);

        $this->db->exec(
            'UPDATE payment_transactions SET notes = :notes, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'notes' => $notes,
                'updated_at' => now_utc(),
                'id' => $paymentId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    private function postPaymentLedger(
        int $stokvelId,
        int $paymentId,
        int $memberId,
        string $amount,
        int $recordedBy,
        string $receiptNumber
    ): int {
        $cashAccount = $this->chartOfAccounts->requireBySubType($stokvelId, 'bank');
        $incomeAccount = $this->chartOfAccounts->requireBySubType($stokvelId, 'contributions');

        $journalId = $this->ledger->createDraft(
            $stokvelId,
            $recordedBy,
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d'),
            $receiptNumber,
            'Contribution payment #' . $paymentId
        );
        $this->ledger->addLine($stokvelId, $journalId, (int) $cashAccount['id'], $amount, '0.00', null, 'Cash received');
        $this->ledger->addLine(
            $stokvelId,
            $journalId,
            (int) $incomeAccount['id'],
            '0.00',
            $amount,
            $memberId,
            'Member contribution'
        );
        $this->ledger->post($stokvelId, $journalId, $recordedBy, true);

        return $journalId;
    }

    private function nextReceiptNumber(int $stokvelId): string
    {
        $year = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y');
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM payment_transactions
             WHERE stokvel_id = :stokvel_id AND transaction_ref LIKE :prefix',
            ['stokvel_id' => $stokvelId, 'prefix' => 'RCP-' . $stokvelId . '-' . $year . '-%']
        );
        $seq = ((int) ($row['cnt'] ?? 0)) + 1;

        return sprintf('RCP-%d-%s-%06d', $stokvelId, $year, $seq);
    }

    private function assertPaymentMethod(string $method): void
    {
        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            throw new FinanceException('Invalid payment method.');
        }
    }

    private function findByIdempotencyKey(int $stokvelId, string $key): ?array
    {
        $row = $this->db->fetch(
            'SELECT id FROM payment_transactions
             WHERE stokvel_id = :stokvel_id AND idempotency_key = :key LIMIT 1',
            ['stokvel_id' => $stokvelId, 'key' => $key]
        );
        if ($row === null) {
            return null;
        }

        return $this->getPayment($stokvelId, (int) $row['id']);
    }

    /** @return list<array<string, mixed>>|null */
    private function findBulkByIdempotencyKey(int $stokvelId, string $key): ?array
    {
        $link = $this->db->fetch(
            'SELECT request_path FROM idempotency_keys
             WHERE stokvel_id = :stokvel_id AND idempotency_key = :key LIMIT 1',
            ['stokvel_id' => $stokvelId, 'key' => $key]
        );
        if ($link === null || !str_starts_with((string) $link['request_path'], '/finance/contributions/bulk:')) {
            return null;
        }

        $ids = explode(',', substr((string) $link['request_path'], strlen('/finance/contributions/bulk:')));
        $results = [];
        foreach ($ids as $id) {
            $payment = $this->getPayment($stokvelId, (int) $id);
            if ($payment !== null) {
                $results[] = $payment;
            }
        }

        return $results !== [] ? $results : null;
    }

    /** @param list<int|string> $paymentIds */
    private function storeBulkIdempotencyKey(int $stokvelId, string $key, array $paymentIds): void
    {
        $now = now_utc();
        $this->db->insert(
            'INSERT INTO idempotency_keys (stokvel_id, idempotency_key, request_method, request_path, expires_at, created_at)
             VALUES (:stokvel_id, :idempotency_key, :request_method, :request_path, :expires_at, :created_at)
             ON DUPLICATE KEY UPDATE request_path = request_path',
            [
                'stokvel_id' => $stokvelId,
                'idempotency_key' => $key,
                'request_method' => 'POST',
                'request_path' => '/finance/contributions/bulk:' . implode(',', $paymentIds),
                'expires_at' => (new \DateTimeImmutable($now))->modify('+30 days')->format('Y-m-d H:i:s'),
                'created_at' => $now,
            ]
        );
    }

    private function normalizePaidAt(string $paidAt): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAt) === 1) {
            return $paidAt . ' 12:00:00';
        }

        return $paidAt;
    }

    /** @param array<string, mixed> $row */
    private function enrichPayment(int $stokvelId, array $row): array
    {
        $meta = $this->parsePaymentNotes($row['notes'] ?? null);
        $allocated = isset($meta['allocated_amount'])
            ? MoneyMath::normalize((string) $meta['allocated_amount'])
            : $this->sumAllocations($stokvelId, (int) $row['id']);
        $credit = isset($meta['credit_amount'])
            ? MoneyMath::normalize((string) $meta['credit_amount'])
            : MoneyMath::max(MoneyMath::sub(MoneyMath::normalize((string) $row['amount']), $allocated), '0.00');

        $row['receipt_number'] = $row['transaction_ref'];
        $row['member_id'] = $row['membership_id'];
        $row['allocated_amount'] = $allocated;
        $row['credit_amount'] = $credit;
        $row['journal_entry_id'] = $meta['journal_entry_id'] ?? null;

        return $row;
    }

    /** @return array<string, mixed> */
    private function parsePaymentNotes(?string $notes): array
    {
        if ($notes === null || $notes === '') {
            return [];
        }
        $decoded = json_decode($notes, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function sumAllocations(int $stokvelId, int $paymentId): string
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM payment_allocations
             WHERE stokvel_id = :stokvel_id AND payment_transaction_id = :payment_id',
            ['stokvel_id' => $stokvelId, 'payment_id' => $paymentId]
        );

        return MoneyMath::normalize($row['total'] ?? '0');
    }
}
