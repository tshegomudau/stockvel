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
            $paidAt = $paidAt ?? $now;

            $paymentId = (int) $this->db->insert(
                'INSERT INTO contribution_payments
                    (stokvel_id, member_id, amount, allocated_amount, credit_amount, payment_method,
                     receipt_number, status, external_reference, idempotency_key, paid_at, recorded_by,
                     created_at, updated_at)
                 VALUES
                    (:stokvel_id, :member_id, :amount, :allocated_amount, :credit_amount, :payment_method,
                     :receipt_number, :status, :external_reference, :idempotency_key, :paid_at, :recorded_by,
                     :created_at, :updated_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'member_id' => $memberId,
                    'amount' => $amount,
                    'allocated_amount' => '0.00',
                    'credit_amount' => '0.00',
                    'payment_method' => $paymentMethod,
                    'receipt_number' => $receiptNumber,
                    'status' => self::STATUS_COMPLETED,
                    'external_reference' => $externalReference,
                    'idempotency_key' => $idempotencyKey,
                    'paid_at' => $paidAt,
                    'recorded_by' => $recordedBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                $this->storeIdempotencyKey($stokvelId, 'contribution_payment', $idempotencyKey, (string) $paymentId);
            }

            $allocation = $this->allocatePaymentToCharges($stokvelId, $paymentId, $memberId, $amount);

            $this->postPaymentLedger($stokvelId, $paymentId, $memberId, $amount, $recordedBy, $receiptNumber);

            $payment = $this->getPayment($stokvelId, $paymentId);
            $payment['allocations'] = $allocation;

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
            $existing = $this->db->fetch(
                'SELECT resource_id FROM idempotency_keys
                 WHERE stokvel_id = :stokvel_id AND scope = :scope AND idempotency_key = :key LIMIT 1',
                [
                    'stokvel_id' => $stokvelId,
                    'scope' => 'contribution_bulk',
                    'key' => $batchIdempotencyKey,
                ]
            );
            if ($existing !== null) {
                return json_decode((string) ($existing['resource_id'] ?? '[]'), true, 512, JSON_THROW_ON_ERROR);
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
            $this->storeIdempotencyKey(
                $stokvelId,
                'contribution_bulk',
                $batchIdempotencyKey,
                json_encode(array_column($results, 'id'), JSON_THROW_ON_ERROR)
            );
        }

        return $results;
    }

    public function getPayment(int $stokvelId, int $paymentId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM contribution_payments WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $paymentId, 'stokvel_id' => $stokvelId]
        );
    }

    public function getMemberCreditBalance(int $stokvelId, int $memberId): string
    {
        $row = $this->db->fetch(
            'SELECT COALESCE(SUM(credit_amount), 0) AS credit FROM contribution_payments
             WHERE stokvel_id = :stokvel_id AND member_id = :member_id AND status = :status',
            [
                'stokvel_id' => $stokvelId,
                'member_id' => $memberId,
                'status' => self::STATUS_COMPLETED,
            ]
        );

        return MoneyMath::normalize($row['credit'] ?? '0');
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
                'INSERT INTO contribution_allocations
                    (stokvel_id, contribution_payment_id, member_charge_id, amount, created_at)
                 VALUES
                    (:stokvel_id, :payment_id, :charge_id, :amount, :created_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'payment_id' => $paymentId,
                    'charge_id' => $charge['id'],
                    'amount' => $apply,
                    'created_at' => now_utc(),
                ]
            );

            $allocations[] = ['charge_id' => (int) $charge['id'], 'amount' => $apply];
            $remaining = MoneyMath::sub($remaining, $apply);
            $allocatedTotal = MoneyMath::add($allocatedTotal, $apply);
        }

        $credit = MoneyMath::normalize($remaining);
        $this->db->exec(
            'UPDATE contribution_payments
             SET allocated_amount = :allocated, credit_amount = :credit, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'allocated' => $allocatedTotal,
                'credit' => $credit,
                'updated_at' => now_utc(),
                'id' => $paymentId,
                'stokvel_id' => $stokvelId,
            ]
        );

        if (MoneyMath::isPositive($credit)) {
            $this->upsertMemberCreditLedger($stokvelId, $memberId, $credit);
        }

        return $allocations;
    }

    private function postPaymentLedger(
        int $stokvelId,
        int $paymentId,
        int $memberId,
        string $amount,
        int $recordedBy,
        string $receiptNumber
    ): void {
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
        $this->ledger->post($stokvelId, $journalId, $recordedBy);

        $this->db->exec(
            'UPDATE contribution_payments SET journal_entry_id = :journal_id, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'journal_id' => $journalId,
                'updated_at' => now_utc(),
                'id' => $paymentId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    private function upsertMemberCreditLedger(int $stokvelId, int $memberId, string $creditAmount): void
    {
        $existing = $this->db->fetch(
            'SELECT id, balance FROM member_credits WHERE stokvel_id = :stokvel_id AND member_id = :member_id LIMIT 1',
            ['stokvel_id' => $stokvelId, 'member_id' => $memberId]
        );
        if ($existing === null) {
            $this->db->insert(
                'INSERT INTO member_credits (stokvel_id, member_id, balance, created_at, updated_at)
                 VALUES (:stokvel_id, :member_id, :balance, :created_at, :updated_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'member_id' => $memberId,
                    'balance' => $creditAmount,
                    'created_at' => now_utc(),
                    'updated_at' => now_utc(),
                ]
            );

            return;
        }

        $newBalance = MoneyMath::add((string) $existing['balance'], $creditAmount);
        $this->db->exec(
            'UPDATE member_credits SET balance = :balance, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'balance' => $newBalance,
                'updated_at' => now_utc(),
                'id' => $existing['id'],
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    private function nextReceiptNumber(int $stokvelId): string
    {
        $year = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y');
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM contribution_payments
             WHERE stokvel_id = :stokvel_id AND receipt_number LIKE :prefix',
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
        $link = $this->db->fetch(
            'SELECT resource_id FROM idempotency_keys
             WHERE stokvel_id = :stokvel_id AND scope = :scope AND idempotency_key = :key LIMIT 1',
            ['stokvel_id' => $stokvelId, 'scope' => 'contribution_payment', 'key' => $key]
        );
        if ($link === null) {
            return null;
        }

        return $this->getPayment($stokvelId, (int) $link['resource_id']);
    }

    private function storeIdempotencyKey(int $stokvelId, string $scope, string $key, string $resourceId): void
    {
        $now = now_utc();
        $this->db->insert(
            'INSERT INTO idempotency_keys (stokvel_id, scope, idempotency_key, resource_id, created_at, expires_at)
             VALUES (:stokvel_id, :scope, :idempotency_key, :resource_id, :created_at, :expires_at)
             ON DUPLICATE KEY UPDATE resource_id = resource_id',
            [
                'stokvel_id' => $stokvelId,
                'scope' => $scope,
                'idempotency_key' => $key,
                'resource_id' => $resourceId,
                'created_at' => $now,
                'expires_at' => (new \DateTimeImmutable($now))->modify('+30 days')->format('Y-m-d H:i:s'),
            ]
        );
    }
}
