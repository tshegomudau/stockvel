<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Exceptions\HttpException;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditService;
use App\Support\MoneyMath;

/**
 * Submit contribution corrections that must pass approval thresholds before applying.
 */
final class ContributionEditRequestService
{
    public function __construct(
        private readonly Database $db,
        private readonly ContributionService $contributions,
        private readonly ApprovalService $approvals,
        private readonly AuditService $audit
    ) {
    }

    /**
     * @param array{
     *   membership_id?:int|string,
     *   amount?:string,
     *   payment_method?:string,
     *   paid_at?:string,
     *   external_reference?:string|null,
     *   edit_reason?:string
     * } $proposed
     * @return array{edit_request_id:int,approval_request_id:int}
     */
    public function submit(int $stokvelId, int $paymentId, int $requestedByUserId, array $proposed): array
    {
        $payment = $this->contributions->getPayment($stokvelId, $paymentId);
        if ($payment === null) {
            throw new HttpException(404, 'Contribution not found.');
        }
        if ((string) ($payment['status'] ?? '') !== ContributionService::STATUS_COMPLETED) {
            throw new FinanceException('Only completed contributions can be edited.');
        }

        $pending = $this->db->fetch(
            "SELECT id FROM contribution_edit_requests
             WHERE stokvel_id = :sid AND payment_transaction_id = :pid AND status = 'pending'
             LIMIT 1",
            ['sid' => $stokvelId, 'pid' => $paymentId]
        );
        if ($pending) {
            throw new FinanceException('This contribution already has a pending edit awaiting approval.');
        }

        $reason = trim((string) ($proposed['edit_reason'] ?? ''));
        if ($reason === '') {
            throw new FinanceException('An edit reason is required.');
        }

        $membershipId = (int) ($proposed['membership_id'] ?? $payment['membership_id']);
        $member = $this->db->fetch(
            'SELECT id FROM memberships WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $membershipId, 'sid' => $stokvelId]
        );
        if ($member === null) {
            throw new FinanceException('Member not found.');
        }

        $amount = MoneyMath::normalize((string) ($proposed['amount'] ?? $payment['amount']));
        if (!MoneyMath::isPositive($amount)) {
            throw new FinanceException('Payment amount must be positive.');
        }

        $method = (string) ($proposed['payment_method'] ?? $payment['payment_method'] ?? 'cash');
        if (!in_array($method, ContributionService::PAYMENT_METHODS, true)) {
            throw new FinanceException('Invalid payment method.');
        }

        $paidAtRaw = (string) ($proposed['paid_at'] ?? $payment['paid_at'] ?? '');
        if ($paidAtRaw === '') {
            throw new FinanceException('Payment date is required.');
        }
        $paidAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAtRaw) === 1
            ? $paidAtRaw . ' 12:00:00'
            : $paidAtRaw;

        $reference = array_key_exists('external_reference', $proposed)
            ? trim((string) ($proposed['external_reference'] ?? ''))
            : (string) ($payment['external_reference'] ?? '');
        if ($reference === '') {
            $reference = null;
        }

        $before = [
            'membership_id' => (int) $payment['membership_id'],
            'amount' => MoneyMath::normalize((string) $payment['amount']),
            'payment_method' => (string) ($payment['payment_method'] ?? ''),
            'paid_at' => (string) ($payment['paid_at'] ?? ''),
            'external_reference' => $payment['external_reference'] ?? null,
            'status' => (string) ($payment['status'] ?? ''),
            'transaction_ref' => (string) ($payment['transaction_ref'] ?? $payment['receipt_number'] ?? ''),
        ];
        $after = [
            'membership_id' => $membershipId,
            'amount' => $amount,
            'payment_method' => $method,
            'paid_at' => $paidAt,
            'external_reference' => $reference,
        ];

        if (
            $before['membership_id'] === $after['membership_id']
            && MoneyMath::equals($before['amount'], $after['amount'])
            && $before['payment_method'] === $after['payment_method']
            && substr($before['paid_at'], 0, 10) === substr($after['paid_at'], 0, 10)
            && (string) ($before['external_reference'] ?? '') === (string) ($after['external_reference'] ?? '')
        ) {
            throw new FinanceException('No changes detected.');
        }

        $payload = [
            'payment_transaction_id' => $paymentId,
            'snapshot_before' => $before,
            'proposed' => $after,
            'edit_reason' => $reason,
        ];

        $thresholdAmount = MoneyMath::comp($before['amount'], $after['amount']) >= 0
            ? $before['amount']
            : $after['amount'];

        $now = now_utc();
        $editId = (int) $this->db->insert(
            'INSERT INTO contribution_edit_requests
                (stokvel_id, payment_transaction_id, requested_by_user_id, status, payload_json, created_at, updated_at)
             VALUES
                (:sid, :pid, :uid, :status, :payload, :ca, :ua)',
            [
                'sid' => $stokvelId,
                'pid' => $paymentId,
                'uid' => $requestedByUserId,
                'status' => 'pending',
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'ca' => $now,
                'ua' => $now,
            ]
        );

        $approvalId = $this->approvals->createRequest(
            $stokvelId,
            'contribution_edit',
            'contribution_edit_request',
            $editId,
            $requestedByUserId,
            $thresholdAmount
        );

        $this->audit->log('contribution_edit.submitted', 'contribution_edit_request', $editId, metadata: [
            'payment_transaction_id' => $paymentId,
            'approval_request_id' => $approvalId,
            'amount' => $thresholdAmount,
        ], stokvelId: $stokvelId, userId: $requestedByUserId);

        return [
            'edit_request_id' => $editId,
            'approval_request_id' => $approvalId,
        ];
    }

    public function applyApproved(int $stokvelId, int $editRequestId, int $actorUserId, string $notes = ''): void
    {
        $row = $this->db->fetch(
            'SELECT * FROM contribution_edit_requests WHERE id = :id AND stokvel_id = :sid LIMIT 1 FOR UPDATE',
            ['id' => $editRequestId, 'sid' => $stokvelId]
        );
        if ($row === null) {
            throw new FinanceException('Contribution edit request not found.');
        }
        if ((string) $row['status'] === 'approved') {
            return;
        }
        if ((string) $row['status'] !== 'pending') {
            throw new FinanceException('Contribution edit request is not pending.');
        }

        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
        if (!is_array($payload) || !isset($payload['proposed']) || !is_array($payload['proposed'])) {
            throw new FinanceException('Contribution edit payload is invalid.');
        }

        $paymentId = (int) ($row['payment_transaction_id'] ?? $payload['payment_transaction_id'] ?? 0);
        $this->contributions->applyApprovedEdit(
            $stokvelId,
            $paymentId,
            $payload['proposed'],
            $actorUserId,
            (string) ($payload['edit_reason'] ?? $notes)
        );

        $now = now_utc();
        $this->db->exec(
            "UPDATE contribution_edit_requests
             SET status = 'approved', decision_notes = :notes, decided_at = :da, decided_by_user_id = :by, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid AND status = 'pending'",
            [
                'notes' => $notes !== '' ? $notes : ($payload['edit_reason'] ?? null),
                'da' => $now,
                'by' => $actorUserId,
                'ua' => $now,
                'id' => $editRequestId,
                'sid' => $stokvelId,
            ]
        );

        $this->audit->log('contribution_edit.approved', 'contribution_edit_request', $editRequestId, metadata: [
            'payment_transaction_id' => $paymentId,
        ], stokvelId: $stokvelId, userId: $actorUserId);
    }

    public function markRejected(int $stokvelId, int $editRequestId, int $actorUserId, string $notes = ''): void
    {
        $row = $this->db->fetch(
            'SELECT * FROM contribution_edit_requests WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $editRequestId, 'sid' => $stokvelId]
        );
        if ($row === null) {
            return;
        }
        if ((string) $row['status'] !== 'pending') {
            return;
        }

        $now = now_utc();
        $this->db->exec(
            "UPDATE contribution_edit_requests
             SET status = 'rejected', decision_notes = :notes, decided_at = :da, decided_by_user_id = :by, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid AND status = 'pending'",
            [
                'notes' => $notes !== '' ? $notes : 'Rejected via approvals',
                'da' => $now,
                'by' => $actorUserId,
                'ua' => $now,
                'id' => $editRequestId,
                'sid' => $stokvelId,
            ]
        );

        $this->audit->log('contribution_edit.rejected', 'contribution_edit_request', $editRequestId, metadata: [
            'payment_transaction_id' => (int) ($row['payment_transaction_id'] ?? 0),
            'reason' => $notes,
        ], stokvelId: $stokvelId, userId: $actorUserId);
    }

    public function findPendingForPayment(int $stokvelId, int $paymentId): ?array
    {
        return $this->db->fetch(
            "SELECT * FROM contribution_edit_requests
             WHERE stokvel_id = :sid AND payment_transaction_id = :pid AND status = 'pending'
             ORDER BY id DESC LIMIT 1",
            ['sid' => $stokvelId, 'pid' => $paymentId]
        ) ?: null;
    }

    /** @return list<int> */
    public function pendingPaymentIds(int $stokvelId, array $paymentIds): array
    {
        $paymentIds = array_values(array_unique(array_filter(array_map('intval', $paymentIds))));
        if ($paymentIds === []) {
            return [];
        }
        $placeholders = [];
        $params = ['sid' => $stokvelId];
        foreach ($paymentIds as $i => $id) {
            $key = 'p' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $rows = $this->db->fetchAll(
            "SELECT payment_transaction_id FROM contribution_edit_requests
             WHERE stokvel_id = :sid AND status = 'pending'
               AND payment_transaction_id IN (" . implode(',', $placeholders) . ')',
            $params
        );
        return array_map(static fn ($r) => (int) $r['payment_transaction_id'], $rows);
    }
}
