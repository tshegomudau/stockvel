<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

/**
 * Member fines — issue, pay, waive, bulk issue with idempotency.
 * Statuses: outstanding|partially_paid|paid|waived|cancelled.
 */
final class FineService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param array{
     *   reason?:?string,
     *   due_date?:?string,
     *   source_type?:?string,
     *   source_id?:?int,
     *   idempotency_key?:?string
     * } $meta
     */
    public function issue(
        int $stokvelId,
        int $membershipId,
        int $fineTypeId,
        string $amount,
        int $issuedByUserId,
        array $meta = []
    ): int {
        $membership = $this->db->fetch(
            'SELECT id FROM memberships WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $membershipId, 'sid' => $stokvelId]
        );
        if (!$membership) {
            throw new FinanceException('Membership not found.');
        }

        $type = $this->db->fetch(
            'SELECT id, name, default_amount, is_active FROM fine_types
             WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $fineTypeId, 'sid' => $stokvelId]
        );
        if (!$type || !(int) ($type['is_active'] ?? 0)) {
            throw new FinanceException('Fine type not found or inactive.');
        }

        $normalized = MoneyMath::normalize($amount !== '' ? $amount : (string) ($type['default_amount'] ?? '0'));
        if ((float) $normalized <= 0) {
            throw new FinanceException('Fine amount must be greater than zero.');
        }

        $key = isset($meta['idempotency_key']) ? trim((string) $meta['idempotency_key']) : '';
        if ($key !== '') {
            $existing = $this->db->fetch(
                'SELECT id FROM fines WHERE stokvel_id = :sid AND idempotency_key = :k LIMIT 1',
                ['sid' => $stokvelId, 'k' => $key]
            );
            if ($existing) {
                return (int) $existing['id'];
            }
        }

        $now = now_utc();
        return (int) $this->db->insert(
            'INSERT INTO fines
                (stokvel_id, membership_id, fine_type_id, fine_type_name_snapshot, amount, amount_paid, waived_amount,
                 reason, status, issued_by_user_id, issued_at, due_date, source_type, source_id, idempotency_key,
                 created_at, updated_at)
             VALUES
                (:sid, :mid, :ftid, :ftname, :amt, 0, 0, :reason, :st, :uid, :issued, :due, :stype, :sid2, :ikey, :ca, :ua)',
            [
                'sid' => $stokvelId,
                'mid' => $membershipId,
                'ftid' => $fineTypeId,
                'ftname' => (string) $type['name'],
                'amt' => $normalized,
                'reason' => isset($meta['reason']) && trim((string) $meta['reason']) !== ''
                    ? trim((string) $meta['reason']) : null,
                'st' => 'outstanding',
                'uid' => $issuedByUserId,
                'issued' => $now,
                'due' => isset($meta['due_date']) && (string) $meta['due_date'] !== ''
                    ? (string) $meta['due_date'] : null,
                'stype' => isset($meta['source_type']) ? (string) $meta['source_type'] : null,
                'sid2' => isset($meta['source_id']) ? (int) $meta['source_id'] : null,
                'ikey' => $key !== '' ? $key : null,
                'ca' => $now,
                'ua' => $now,
            ]
        );
    }

    public function recordPayment(int $stokvelId, int $fineId, string $amount): void
    {
        $fine = $this->requireFine($stokvelId, $fineId);
        $status = (string) ($fine['status'] ?? '');
        if (in_array($status, ['waived', 'cancelled', 'paid'], true)) {
            throw new FinanceException('Cannot record payment against a ' . $status . ' fine.');
        }

        $pay = MoneyMath::normalize($amount);
        if ((float) $pay <= 0) {
            throw new FinanceException('Payment amount must be greater than zero.');
        }

        $newPaid = MoneyMath::add((string) $fine['amount_paid'], $pay);
        $outstanding = MoneyMath::sub(
            MoneyMath::sub((string) $fine['amount'], (string) $fine['waived_amount']),
            (string) $fine['amount_paid']
        );
        if (MoneyMath::comp($pay, $outstanding) > 0) {
            throw new FinanceException(
                'Payment exceeds outstanding balance of R ' . number_format((float) $outstanding, 2) . '.'
            );
        }

        $newStatus = $this->deriveStatus(
            (string) $fine['amount'],
            $newPaid,
            (string) $fine['waived_amount'],
            $status
        );

        $this->db->exec(
            'UPDATE fines SET amount_paid = :paid, status = :st, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid',
            [
                'paid' => $newPaid,
                'st' => $newStatus,
                'ua' => now_utc(),
                'id' => $fineId,
                'sid' => $stokvelId,
            ]
        );
    }

    public function waive(
        int $stokvelId,
        int $fineId,
        int $userId,
        string $reason,
        ?string $waivedAmount = null
    ): void {
        $fine = $this->requireFine($stokvelId, $fineId);
        $status = (string) ($fine['status'] ?? '');
        if (in_array($status, ['waived', 'cancelled', 'paid'], true)) {
            throw new FinanceException('Cannot waive a ' . $status . ' fine.');
        }

        $remaining = MoneyMath::sub(
            MoneyMath::sub((string) $fine['amount'], (string) $fine['waived_amount']),
            (string) $fine['amount_paid']
        );
        $toWaive = $waivedAmount !== null && trim($waivedAmount) !== ''
            ? MoneyMath::normalize($waivedAmount)
            : $remaining;

        if ((float) $toWaive <= 0) {
            throw new FinanceException('Waive amount must be greater than zero.');
        }
        if (MoneyMath::comp($toWaive, $remaining) > 0) {
            throw new FinanceException('Waive amount exceeds outstanding balance.');
        }

        $newWaived = MoneyMath::add((string) $fine['waived_amount'], $toWaive);
        $newStatus = $this->deriveStatus(
            (string) $fine['amount'],
            (string) $fine['amount_paid'],
            $newWaived,
            'waived'
        );

        $this->db->exec(
            'UPDATE fines
             SET waived_amount = :waived, status = :st, waived_at = :wat, waived_by_user_id = :uid,
                 waived_reason = :reason, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid',
            [
                'waived' => $newWaived,
                'st' => $newStatus,
                'wat' => now_utc(),
                'uid' => $userId,
                'reason' => $reason !== '' ? $reason : 'Waived by administrator',
                'ua' => now_utc(),
                'id' => $fineId,
                'sid' => $stokvelId,
            ]
        );
    }

    /**
     * Issue the same fine type/amount to many members.
     * When idempotency_key is set, each membership uses "{key}:{membershipId}".
     *
     * @param list<int> $membershipIds
     * @param array{reason?:?string,due_date?:?string,idempotency_key?:?string} $meta
     * @return list<int> Fine IDs issued (or existing via idempotency)
     */
    public function bulkIssue(
        int $stokvelId,
        array $membershipIds,
        int $fineTypeId,
        string $amount,
        int $issuedByUserId,
        array $meta = []
    ): array {
        $ids = [];
        $baseKey = isset($meta['idempotency_key']) ? trim((string) $meta['idempotency_key']) : '';
        foreach ($membershipIds as $membershipId) {
            $mid = (int) $membershipId;
            if ($mid < 1) {
                continue;
            }
            $rowMeta = $meta;
            if ($baseKey !== '') {
                $rowMeta['idempotency_key'] = $baseKey . ':' . $mid;
            } else {
                unset($rowMeta['idempotency_key']);
            }
            $ids[] = $this->issue($stokvelId, $mid, $fineTypeId, $amount, $issuedByUserId, $rowMeta);
        }
        return $ids;
    }

    /** @return list<array<string, mixed>> */
    public function listForMember(int $stokvelId, int $membershipId): array
    {
        return $this->db->fetchAll(
            'SELECT f.*, ft.name AS fine_type_name
             FROM fines f
             LEFT JOIN fine_types ft ON ft.id = f.fine_type_id
             WHERE f.stokvel_id = :sid AND f.membership_id = :mid
             ORDER BY f.issued_at DESC, f.id DESC',
            ['sid' => $stokvelId, 'mid' => $membershipId]
        );
    }

    private function requireFine(int $stokvelId, int $fineId): array
    {
        $fine = $this->db->fetch(
            'SELECT * FROM fines WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $fineId, 'sid' => $stokvelId]
        );
        if (!$fine) {
            throw new FinanceException('Fine not found.');
        }
        return $fine;
    }

    private function deriveStatus(string $amount, string $paid, string $waived, string $hint): string
    {
        $covered = MoneyMath::add($paid, $waived);
        if (MoneyMath::comp($waived, $amount) >= 0 && MoneyMath::isZero($paid)) {
            return 'waived';
        }
        if (MoneyMath::comp($covered, $amount) >= 0) {
            return MoneyMath::comp($waived, '0') > 0 && MoneyMath::comp($paid, '0') === 0
                ? 'waived'
                : 'paid';
        }
        if (MoneyMath::comp($paid, '0') > 0) {
            return 'partially_paid';
        }
        if ($hint === 'waived' && MoneyMath::comp($waived, '0') > 0 && MoneyMath::isZero($paid)
            && MoneyMath::comp($covered, $amount) >= 0) {
            return 'waived';
        }
        return 'outstanding';
    }
}
