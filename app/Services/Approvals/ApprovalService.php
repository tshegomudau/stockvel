<?php

declare(strict_types=1);

namespace App\Services\Approvals;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Services\Loans\LoanService;
use App\Support\MoneyMath;

/**
 * Multi-approver workflow over approval_requests + approval_decisions.
 * Overall status stays pending until min_approvals (and optional role rules) are met.
 * A single rejection (with required reason) finalises as rejected.
 */
final class ApprovalService
{
    /**
     * Known policy request types (business catalogue for settings UI).
     * Custom types may still be stored; blank/null = default policy.
     *
     * @var array<string, string>
     */
    public const REQUEST_TYPE_OPTIONS = [
        '' => 'Default (any type)',
        'loan_application' => 'Loan application',
        'expense' => 'Expense',
        'payout' => 'Payout',
        'burial_claim' => 'Burial claim',
        'fund_debit' => 'Fund debit',
        'fine_waiver' => 'Fine waiver',
        'member_change' => 'Member change request',
    ];

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Resolve how many approvals are required for a request type + optional amount.
     *
     * @return array{min_approvals:int,required_role_codes:list<string>,policy_id:?int}
     */
    public function resolvePolicy(int $stokvelId, string $requestType, ?string $amount = null): array
    {
        $settings = $this->db->fetch(
            'SELECT require_dual_approval, default_min_approvals FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        );
        $fallback = (int) ($settings['default_min_approvals'] ?? 0);
        if ($fallback < 1) {
            $fallback = !empty($settings['require_dual_approval']) ? 2 : 1;
        }

        $policies = $this->db->fetchAll(
            'SELECT * FROM approval_policies
             WHERE stokvel_id = :sid AND is_active = 1
               AND (request_type IS NULL OR request_type = :rtype OR request_type = :empty)
             ORDER BY
                CASE WHEN request_type = :rtype2 THEN 0 WHEN request_type IS NULL OR request_type = :empty2 THEN 1 ELSE 2 END,
                amount_threshold DESC',
            [
                'sid' => $stokvelId,
                'rtype' => $requestType,
                'empty' => '',
                'rtype2' => $requestType,
                'empty2' => '',
            ]
        );

        $chosen = null;
        $normalizedAmount = $amount !== null && $amount !== '' ? MoneyMath::normalize($amount) : null;

        foreach ($policies as $policy) {
            $threshold = $policy['amount_threshold'] ?? null;
            if ($threshold === null || $threshold === '') {
                if ($chosen === null) {
                    $chosen = $policy;
                }
                continue;
            }
            if ($normalizedAmount === null) {
                continue;
            }
            if (MoneyMath::comp($normalizedAmount, (string) $threshold) >= 0) {
                $chosen = $policy;
                break;
            }
        }

        if ($chosen === null) {
            return [
                'min_approvals' => max(1, $fallback),
                'required_role_codes' => [],
                'policy_id' => null,
            ];
        }

        $roles = [];
        if (!empty($chosen['required_role_codes'])) {
            $decoded = json_decode((string) $chosen['required_role_codes'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $code) {
                    $code = trim((string) $code);
                    if ($code !== '') {
                        $roles[] = $code;
                    }
                }
            }
        }

        return [
            'min_approvals' => max(1, (int) ($chosen['min_approvals'] ?? $fallback)),
            'required_role_codes' => $roles,
            'policy_id' => (int) $chosen['id'],
        ];
    }

    /**
     * Create a pending approval request with policy snapshot.
     *
     * @return int approval_request id
     */
    public function createRequest(
        int $stokvelId,
        string $requestType,
        string $referenceType,
        int $referenceId,
        int $requestedByUserId,
        ?string $amount = null
    ): int {
        $existing = $this->db->fetch(
            "SELECT id FROM approval_requests
             WHERE stokvel_id = :sid AND reference_type = :reftype AND reference_id = :refid
               AND status = 'pending' LIMIT 1",
            ['sid' => $stokvelId, 'reftype' => $referenceType, 'refid' => $referenceId]
        );
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $policy = $this->resolvePolicy($stokvelId, $requestType, $amount);
        $now = now_utc();
        $normalizedAmount = null;
        if ($amount !== null && $amount !== '') {
            $normalizedAmount = MoneyMath::normalize($amount);
        }

        return (int) $this->db->insert(
            'INSERT INTO approval_requests
                (stokvel_id, request_type, reference_type, reference_id, status, amount,
                 required_approvals, approval_count, requested_by_user_id, requested_at, created_at, updated_at)
             VALUES
                (:sid, :rtype, :reftype, :refid, :status, :amount,
                 :req, 0, :by, :ra, :ca, :ua)',
            [
                'sid' => $stokvelId,
                'rtype' => $requestType,
                'reftype' => $referenceType,
                'refid' => $referenceId,
                'status' => 'pending',
                'amount' => $normalizedAmount,
                'req' => $policy['min_approvals'],
                'by' => $requestedByUserId,
                'ra' => $now,
                'ca' => $now,
                'ua' => $now,
            ]
        );
    }

    /** @return list<array<string, mixed>> */
    public function decisionsFor(int $stokvelId, int $requestId): array
    {
        return $this->db->fetchAll(
            "SELECT ad.*,
                    CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, '')) AS actor_name,
                    u.email AS actor_email
             FROM approval_decisions ad
             JOIN users u ON u.id = ad.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE ad.stokvel_id = :sid AND ad.approval_request_id = :rid
             ORDER BY ad.decided_at ASC",
            ['sid' => $stokvelId, 'rid' => $requestId]
        );
    }

    /**
     * Record an independent approval. Side-effects run only when requirements are met.
     *
     * @return array{status:string,approval_count:int,required_approvals:int,finalised:bool}
     */
    public function approve(int $stokvelId, int $requestId, int $userId, string $notes = ''): array
    {
        $row = $this->requirePending($stokvelId, $requestId);
        $this->assertNotRequester($row, $userId);
        $this->assertNoPriorDecision($stokvelId, $requestId, $userId);

        $now = now_utc();
        $this->db->insert(
            'INSERT INTO approval_decisions
                (stokvel_id, approval_request_id, user_id, decision, reason, decided_at, created_at)
             VALUES
                (:sid, :rid, :uid, :dec, :reason, :da, :ca)',
            [
                'sid' => $stokvelId,
                'rid' => $requestId,
                'uid' => $userId,
                'dec' => 'approved',
                'reason' => $notes !== '' ? $notes : null,
                'da' => $now,
                'ca' => $now,
            ]
        );

        $approvalCount = $this->countApprovals($stokvelId, $requestId);
        $required = max(1, (int) ($row['required_approvals'] ?? 1));

        $this->db->exec(
            'UPDATE approval_requests SET approval_count = :cnt, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid',
            ['cnt' => $approvalCount, 'ua' => $now, 'id' => $requestId, 'sid' => $stokvelId]
        );

        $rolesOk = $this->requiredRolesSatisfied($stokvelId, $requestId, (string) ($row['request_type'] ?? ''), $row['amount'] ?? null);
        $finalised = $approvalCount >= $required && $rolesOk;

        if ($finalised) {
            $this->db->exec(
                "UPDATE approval_requests
                 SET status = 'approved', approved_by_user_id = :by, rejected_by_user_id = NULL,
                     decision_notes = :notes, decided_at = :da, approval_count = :cnt, updated_at = :ua
                 WHERE id = :id AND stokvel_id = :sid AND status = 'pending'",
                [
                    'by' => $userId,
                    'notes' => $notes !== '' ? $notes : null,
                    'da' => $now,
                    'cnt' => $approvalCount,
                    'ua' => $now,
                    'id' => $requestId,
                    'sid' => $stokvelId,
                ]
            );
            $this->applySideEffects($row, 'approved', $notes, $userId);
        }

        return [
            'status' => $finalised ? 'approved' : 'pending',
            'approval_count' => $approvalCount,
            'required_approvals' => $required,
            'finalised' => $finalised,
        ];
    }

    /**
     * Rejection requires a non-empty reason and finalises the request immediately.
     *
     * @return array{status:string,approval_count:int,required_approvals:int,finalised:bool}
     */
    public function reject(int $stokvelId, int $requestId, int $userId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new FinanceException('A rejection reason is required.');
        }

        $row = $this->requirePending($stokvelId, $requestId);
        $this->assertNotRequester($row, $userId);
        $this->assertNoPriorDecision($stokvelId, $requestId, $userId);

        $now = now_utc();
        $this->db->insert(
            'INSERT INTO approval_decisions
                (stokvel_id, approval_request_id, user_id, decision, reason, decided_at, created_at)
             VALUES
                (:sid, :rid, :uid, :dec, :reason, :da, :ca)',
            [
                'sid' => $stokvelId,
                'rid' => $requestId,
                'uid' => $userId,
                'dec' => 'rejected',
                'reason' => $reason,
                'da' => $now,
                'ca' => $now,
            ]
        );

        $approvalCount = $this->countApprovals($stokvelId, $requestId);

        $this->db->exec(
            "UPDATE approval_requests
             SET status = 'rejected', rejected_by_user_id = :by, approved_by_user_id = NULL,
                 decision_notes = :notes, decided_at = :da, approval_count = :cnt, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid AND status = 'pending'",
            [
                'by' => $userId,
                'notes' => $reason,
                'da' => $now,
                'cnt' => $approvalCount,
                'ua' => $now,
                'id' => $requestId,
                'sid' => $stokvelId,
            ]
        );

        $this->applySideEffects($row, 'rejected', $reason, $userId);

        return [
            'status' => 'rejected',
            'approval_count' => $approvalCount,
            'required_approvals' => max(1, (int) ($row['required_approvals'] ?? 1)),
            'finalised' => true,
        ];
    }

    /** Upsert a simple default / typed policy (settings UI). */
    public function upsertPolicy(
        int $stokvelId,
        ?string $requestType,
        int $minApprovals,
        ?string $amountThreshold = null,
        array $requiredRoleCodes = []
    ): int {
        $minApprovals = max(1, $minApprovals);
        $threshold = null;
        if ($amountThreshold !== null && $amountThreshold !== '') {
            $threshold = MoneyMath::normalize($amountThreshold);
        }
        $rolesJson = $requiredRoleCodes !== [] ? json_encode(array_values($requiredRoleCodes)) : null;
        $now = now_utc();

        $sql = 'SELECT id FROM approval_policies WHERE stokvel_id = :sid AND is_active = 1';
        $params = ['sid' => $stokvelId];
        if ($requestType === null || $requestType === '') {
            $sql .= ' AND (request_type IS NULL OR request_type = \'\')';
        } else {
            $sql .= ' AND request_type = :rtype';
            $params['rtype'] = $requestType;
        }
        if ($threshold === null) {
            $sql .= ' AND amount_threshold IS NULL';
        } else {
            $sql .= ' AND amount_threshold = :thr';
            $params['thr'] = $threshold;
        }
        $sql .= ' LIMIT 1';
        $existing = $this->db->fetch($sql, $params);

        if ($existing) {
            $this->db->exec(
                'UPDATE approval_policies
                 SET min_approvals = :min, required_role_codes = :roles, is_active = 1, updated_at = :ua
                 WHERE id = :id AND stokvel_id = :sid',
                [
                    'min' => $minApprovals,
                    'roles' => $rolesJson,
                    'ua' => $now,
                    'id' => (int) $existing['id'],
                    'sid' => $stokvelId,
                ]
            );
            return (int) $existing['id'];
        }

        return (int) $this->db->insert(
            'INSERT INTO approval_policies
                (stokvel_id, request_type, min_approvals, amount_threshold, required_role_codes, is_active, created_at, updated_at)
             VALUES
                (:sid, :rtype, :min, :thr, :roles, 1, :ca, :ua)',
            [
                'sid' => $stokvelId,
                'rtype' => $requestType,
                'min' => $minApprovals,
                'thr' => $threshold,
                'roles' => $rolesJson,
                'ca' => $now,
                'ua' => $now,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPolicies(int $stokvelId, bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM approval_policies WHERE stokvel_id = :sid';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY is_active DESC, request_type IS NULL DESC, request_type ASC, amount_threshold ASC, id ASC';

        return $this->db->fetchAll($sql, ['sid' => $stokvelId]);
    }

    public function findPolicy(int $stokvelId, int $policyId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM approval_policies WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $policyId, 'sid' => $stokvelId]
        );
    }

    public function updatePolicy(
        int $stokvelId,
        int $policyId,
        int $minApprovals,
        ?string $amountThreshold = null,
        array $requiredRoleCodes = []
    ): void {
        $existing = $this->findPolicy($stokvelId, $policyId);
        if ($existing === null) {
            throw new FinanceException('Approval policy not found.');
        }

        $minApprovals = max(1, $minApprovals);
        $threshold = null;
        if ($amountThreshold !== null && $amountThreshold !== '') {
            $threshold = MoneyMath::normalize($amountThreshold);
        }
        $rolesJson = $requiredRoleCodes !== [] ? json_encode(array_values($requiredRoleCodes)) : null;

        $this->db->exec(
            'UPDATE approval_policies
             SET min_approvals = :min, amount_threshold = :thr, required_role_codes = :roles, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid',
            [
                'min' => $minApprovals,
                'thr' => $threshold,
                'roles' => $rolesJson,
                'ua' => now_utc(),
                'id' => $policyId,
                'sid' => $stokvelId,
            ]
        );
    }

    public function setPolicyActive(int $stokvelId, int $policyId, bool $active): void
    {
        $existing = $this->findPolicy($stokvelId, $policyId);
        if ($existing === null) {
            throw new FinanceException('Approval policy not found.');
        }
        $this->db->exec(
            'UPDATE approval_policies SET is_active = :active, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            [
                'active' => $active ? 1 : 0,
                'ua' => now_utc(),
                'id' => $policyId,
                'sid' => $stokvelId,
            ]
        );
    }

    public function deletePolicy(int $stokvelId, int $policyId): void
    {
        $existing = $this->findPolicy($stokvelId, $policyId);
        if ($existing === null) {
            throw new FinanceException('Approval policy not found.');
        }
        $this->db->exec(
            'DELETE FROM approval_policies WHERE id = :id AND stokvel_id = :sid',
            ['id' => $policyId, 'sid' => $stokvelId]
        );
    }

    /** @param array<string, mixed> $row */
    private function requirePending(int $stokvelId, int $requestId): array
    {
        $row = $this->db->fetch(
            'SELECT * FROM approval_requests WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $requestId, 'sid' => $stokvelId]
        );
        if ($row === null || ($row['status'] ?? '') !== 'pending') {
            throw new FinanceException('Approval request not found or already decided.');
        }
        return $row;
    }

    /** @param array<string, mixed> $row */
    private function assertNotRequester(array $row, int $userId): void
    {
        if ((int) ($row['requested_by_user_id'] ?? 0) === $userId) {
            throw new FinanceException('You cannot approve or reject a request you submitted.');
        }
    }

    private function assertNoPriorDecision(int $stokvelId, int $requestId, int $userId): void
    {
        $existing = $this->db->fetch(
            'SELECT id FROM approval_decisions
             WHERE stokvel_id = :sid AND approval_request_id = :rid AND user_id = :uid LIMIT 1',
            ['sid' => $stokvelId, 'rid' => $requestId, 'uid' => $userId]
        );
        if ($existing !== null) {
            throw new FinanceException('You have already recorded a decision on this request.');
        }
    }

    private function countApprovals(int $stokvelId, int $requestId): int
    {
        return (int) ($this->db->fetch(
            "SELECT COUNT(*) AS c FROM approval_decisions
             WHERE stokvel_id = :sid AND approval_request_id = :rid AND decision = 'approved'",
            ['sid' => $stokvelId, 'rid' => $requestId]
        )['c'] ?? 0);
    }

    private function requiredRolesSatisfied(int $stokvelId, int $requestId, string $requestType, mixed $amount): bool
    {
        $policy = $this->resolvePolicy(
            $stokvelId,
            $requestType,
            $amount !== null && $amount !== '' ? (string) $amount : null
        );
        $needed = $policy['required_role_codes'];
        if ($needed === []) {
            return true;
        }

        $approverIds = $this->db->fetchAll(
            "SELECT user_id FROM approval_decisions
             WHERE stokvel_id = :sid AND approval_request_id = :rid AND decision = 'approved'",
            ['sid' => $stokvelId, 'rid' => $requestId]
        );
        if ($approverIds === []) {
            return false;
        }

        $userIds = array_map(static fn ($r) => (int) $r['user_id'], $approverIds);
        $placeholders = [];
        $params = ['sid' => $stokvelId];
        foreach ($userIds as $i => $uid) {
            $key = 'u' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $uid;
        }

        $rolePlaceholders = [];
        foreach ($needed as $i => $code) {
            $key = 'r' . $i;
            $rolePlaceholders[] = ':' . $key;
            $params[$key] = $code;
        }

        $found = $this->db->fetchAll(
            'SELECT DISTINCT r.code
             FROM membership_roles mr
             JOIN memberships m ON m.id = mr.membership_id AND m.stokvel_id = mr.stokvel_id
             JOIN roles r ON r.id = mr.role_id
             WHERE mr.stokvel_id = :sid
               AND m.user_id IN (' . implode(',', $placeholders) . ')
               AND r.code IN (' . implode(',', $rolePlaceholders) . ')',
            $params
        );
        $have = array_map(static fn ($r) => (string) $r['code'], $found);
        foreach ($needed as $code) {
            if (!in_array($code, $have, true)) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string, mixed> $row */
    private function applySideEffects(array $row, string $status, string $notes, int $userId): void
    {
        $sid = (int) $row['stokvel_id'];
        $refType = (string) ($row['reference_type'] ?? $row['request_type'] ?? '');
        $refId = (int) ($row['reference_id'] ?? 0);
        if ($refId < 1) {
            return;
        }

        if (in_array($refType, ['loan_application', 'loan'], true)) {
            /** @var LoanService $loans */
            $loans = app()->container()->get(LoanService::class);
            if ($status === 'approved') {
                $loans->committeeApprove(
                    $sid,
                    $refId,
                    $userId,
                    null,
                    $notes !== '' ? $notes : 'Approved via approvals dashboard'
                );
            } else {
                $loans->committeeReject(
                    $sid,
                    $refId,
                    $userId,
                    $notes !== '' ? $notes : 'Rejected via approvals dashboard'
                );
            }
            return;
        }

        if ($refType === 'meeting_notes') {
            $this->db->exec(
                'UPDATE meetings SET status = :st, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
                [
                    'st' => $status === 'approved' ? 'completed' : 'scheduled',
                    'ua' => now_utc(),
                    'id' => $refId,
                    'sid' => $sid,
                ]
            );
            return;
        }

        if ($refType === 'burial_claim') {
            $this->db->exec(
                'UPDATE burial_claims
                 SET status = :st, decided_by_user_id = :by, decided_at = :da, updated_at = :ua
                 WHERE id = :id AND stokvel_id = :sid',
                [
                    'st' => $status === 'approved' ? 'approved' : 'rejected',
                    'by' => $userId,
                    'da' => now_utc(),
                    'ua' => now_utc(),
                    'id' => $refId,
                    'sid' => $sid,
                ]
            );
        }
    }
}
