<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Core\Database;
use App\Support\SensitiveData;

final class MemberChangeRequestService
{
    public function __construct(
        private readonly Database $db
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function submit(
        int $stokvelId,
        int $membershipId,
        int $requestedByUserId,
        string $changeType,
        array $payload
    ): int {
        if (str_starts_with($changeType, 'beneficiary_') && $changeType !== 'beneficiary_delete') {
            $this->assertBeneficiaryShares($stokvelId, $membershipId, $changeType, $payload);
        }

        $payload = $this->sanitizePayload($changeType, $payload);
        $now = now_utc();
        $changeId = (int) $this->db->insert(
            'INSERT INTO member_change_requests
                (stokvel_id, membership_id, requested_by_user_id, change_type, payload_json, status, created_at, updated_at)
             VALUES
                (:sid, :mid, :by, :type, :payload, :status, :ca, :ua)',
            [
                'sid' => $stokvelId,
                'mid' => $membershipId,
                'by' => $requestedByUserId,
                'type' => $changeType,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'status' => 'pending_member',
                'ca' => $now,
                'ua' => $now,
            ]
        );

        $this->createCommitteeApprovalRequest($stokvelId, $changeId, $requestedByUserId, $now);
        $this->notifyMember($stokvelId, $membershipId, $changeType, $now);

        return $changeId;
    }

    public function approve(int $changeRequestId, int $membershipId, int $stokvelId, ?string $notes = null): void
    {
        $row = $this->loadPending($changeRequestId, $membershipId, $stokvelId);
        if ($row === null) {
            throw new \RuntimeException('Change request not found or already decided.');
        }

        $payload = $this->decodePayload($row);
        $now = now_utc();

        $this->db->transaction(function () use ($row, $payload, $changeRequestId, $notes, $now, $stokvelId): void {
            $this->applyChange((string) $row['change_type'], $payload, (int) $row['membership_id'], $stokvelId, $now);

            $this->db->exec(
                'UPDATE member_change_requests
                 SET status = :status, member_decided_at = :decided, member_decision_notes = :notes, updated_at = :ua
                 WHERE id = :id',
                [
                    'status' => 'approved',
                    'decided' => $now,
                    'notes' => $notes,
                    'ua' => $now,
                    'id' => $changeRequestId,
                ]
            );

            $this->finalizeCommitteeVisibility($stokvelId, $changeRequestId, 'approved', $now);
        });
    }

    public function reject(int $changeRequestId, int $membershipId, int $stokvelId, ?string $notes = null): void
    {
        $row = $this->loadPending($changeRequestId, $membershipId, $stokvelId);
        if ($row === null) {
            throw new \RuntimeException('Change request not found or already decided.');
        }

        $now = now_utc();
        $this->db->exec(
            'UPDATE member_change_requests
             SET status = :status, member_decided_at = :decided, member_decision_notes = :notes, updated_at = :ua
             WHERE id = :id',
            [
                'status' => 'rejected',
                'decided' => $now,
                'notes' => $notes,
                'ua' => $now,
                'id' => $changeRequestId,
            ]
        );
        $this->finalizeCommitteeVisibility($stokvelId, $changeRequestId, 'rejected', $now);
    }

    /** @return array<string, mixed>|null */
    private function loadPending(int $changeRequestId, int $membershipId, int $stokvelId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM member_change_requests
             WHERE id = :id AND membership_id = :mid AND stokvel_id = :sid AND status = :status
             LIMIT 1',
            [
                'id' => $changeRequestId,
                'mid' => $membershipId,
                'sid' => $stokvelId,
                'status' => 'pending_member',
            ]
        );
    }

    /** @param array<string, mixed> $row */
    /** @return array<string, mixed> */
    private function decodePayload(array $row): array
    {
        $raw = $row['payload_json'] ?? '{}';
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($raw) ? $raw : [];
    }

    /**
     * Never persist plain SA ID numbers in payload_json.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(string $changeType, array $payload): array
    {
        $plain = trim((string) ($payload['id_number'] ?? ''));
        unset($payload['id_number']);

        if ($plain !== '' && !str_contains($plain, '•')) {
            $payload['id_number_encrypted'] = SensitiveData::encrypt($plain);
            $payload['id_number_hash'] = SensitiveData::hash($plain);
            $payload['id_number_masked'] = SensitiveData::maskId($plain);
        }

        if ($changeType === 'profile_update' && $plain === '') {
            // Leave ID unchanged on apply.
            unset($payload['id_number_encrypted'], $payload['id_number_hash'], $payload['id_number_masked']);
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function assertBeneficiaryShares(
        int $stokvelId,
        int $membershipId,
        string $changeType,
        array $payload
    ): void {
        $rows = $this->db->fetchAll(
            "SELECT id, share_percent FROM beneficiaries
             WHERE stokvel_id = :sid AND membership_id = :mid AND (status = 'active' OR status IS NULL OR status = '')",
            ['sid' => $stokvelId, 'mid' => $membershipId]
        );

        $shares = [];
        foreach ($rows as $row) {
            $shares[(int) $row['id']] = (float) $row['share_percent'];
        }

        $newShare = (float) ($payload['share_percent'] ?? 100);
        if ($newShare < 0 || $newShare > 100) {
            throw new \RuntimeException('Beneficiary share must be between 0 and 100%.');
        }

        if ($changeType === 'beneficiary_create') {
            $total = array_sum($shares) + $newShare;
        } else {
            $bid = (int) ($payload['beneficiary_id'] ?? 0);
            $shares[$bid] = $newShare;
            $total = array_sum($shares);
        }

        if (round($total, 2) !== 100.0) {
            throw new \RuntimeException(
                'Beneficiary shares must total exactly 100% (would be ' . number_format($total, 2) . '%). Adjust shares so they add up to 100%.'
            );
        }
    }

    /** @param array<string, mixed> $payload */
    private function applyChange(string $changeType, array $payload, int $membershipId, int $stokvelId, string $now): void
    {
        match ($changeType) {
            'profile_update' => $this->applyProfileUpdate($membershipId, $payload, $now),
            'beneficiary_create' => $this->applyBeneficiaryCreate($membershipId, $stokvelId, $payload, $now),
            'beneficiary_update' => $this->applyBeneficiaryUpdate($membershipId, $stokvelId, $payload, $now),
            'beneficiary_delete' => $this->applyBeneficiaryDelete($membershipId, $stokvelId, $payload),
            default => throw new \RuntimeException('Unknown change type: ' . $changeType),
        };
    }

    /** @param array<string, mixed> $payload */
    private function applyProfileUpdate(int $membershipId, array $payload, string $now): void
    {
        $member = $this->db->fetch(
            'SELECT user_id FROM memberships WHERE id = :id LIMIT 1',
            ['id' => $membershipId]
        );
        if (!$member) {
            throw new \RuntimeException('Membership not found.');
        }
        $userId = (int) $member['user_id'];

        $mobile = trim((string) ($payload['mobile'] ?? ''));
        $this->db->exec(
            'UPDATE users SET mobile = :mobile, updated_at = :ua WHERE id = :id',
            ['mobile' => $mobile !== '' ? $mobile : null, 'ua' => $now, 'id' => $userId]
        );

        $firstName = trim((string) ($payload['first_name'] ?? ''));
        $lastName = trim((string) ($payload['last_name'] ?? ''));
        $enc = isset($payload['id_number_encrypted']) ? (string) $payload['id_number_encrypted'] : null;
        $hash = isset($payload['id_number_hash']) ? (string) $payload['id_number_hash'] : null;

        $profile = $this->db->fetch('SELECT id FROM user_profiles WHERE user_id = :id LIMIT 1', ['id' => $userId]);
        if ($profile) {
            if ($enc !== null && $enc !== '') {
                $this->db->exec(
                    'UPDATE user_profiles SET first_name = :fn, last_name = :ln,
                        id_number = NULL, id_number_encrypted = :enc, id_number_hash = :hash, updated_at = :ua
                     WHERE user_id = :id',
                    [
                        'fn' => $firstName,
                        'ln' => $lastName,
                        'enc' => $enc,
                        'hash' => $hash,
                        'ua' => $now,
                        'id' => $userId,
                    ]
                );
            } else {
                $this->db->exec(
                    'UPDATE user_profiles SET first_name = :fn, last_name = :ln, updated_at = :ua WHERE user_id = :id',
                    ['fn' => $firstName, 'ln' => $lastName, 'ua' => $now, 'id' => $userId]
                );
            }
        } else {
            $this->db->insert(
                'INSERT INTO user_profiles (user_id, first_name, last_name, id_number_encrypted, id_number_hash, created_at, updated_at)
                 VALUES (:id, :fn, :ln, :enc, :hash, :ca, :ua)',
                [
                    'id' => $userId,
                    'fn' => $firstName,
                    'ln' => $lastName,
                    'enc' => $enc,
                    'hash' => $hash,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
        }
    }

    /** @param array<string, mixed> $payload */
    private function applyBeneficiaryCreate(int $membershipId, int $stokvelId, array $payload, string $now): void
    {
        // beneficiaries.id_number is VARCHAR(13) — store masked form only, never plain SA ID.
        $this->db->insert(
            'INSERT INTO beneficiaries
                (stokvel_id, membership_id, full_name, id_number, relationship, share_percent, phone, email, is_primary, created_at, updated_at)
             VALUES
                (:sid, :mid, :name, :idn, :rel, :share, :phone, :email, :primary, :ca, :ua)',
            [
                'sid' => $stokvelId,
                'mid' => $membershipId,
                'name' => trim((string) ($payload['full_name'] ?? '')),
                'idn' => $this->nullableString($payload['id_number_masked'] ?? null),
                'rel' => trim((string) ($payload['relationship'] ?? '')),
                'share' => (float) ($payload['share_percent'] ?? 100),
                'phone' => $this->nullableString($payload['phone'] ?? null),
                'email' => $this->nullableString($payload['email'] ?? null),
                'primary' => !empty($payload['is_primary']) ? 1 : 0,
                'ca' => $now,
                'ua' => $now,
            ]
        );
    }

    /** @param array<string, mixed> $payload */
    private function applyBeneficiaryUpdate(int $membershipId, int $stokvelId, array $payload, string $now): void
    {
        $beneficiaryId = (int) ($payload['beneficiary_id'] ?? 0);
        if ($beneficiaryId <= 0) {
            throw new \RuntimeException('Beneficiary id required.');
        }

        $idn = array_key_exists('id_number_masked', $payload)
            ? $this->nullableString($payload['id_number_masked'] ?? null)
            : null;

        if ($idn !== null) {
            $this->db->exec(
                'UPDATE beneficiaries SET
                    full_name = :name, id_number = :idn, relationship = :rel, share_percent = :share,
                    phone = :phone, email = :email, is_primary = :primary, updated_at = :ua
                 WHERE id = :id AND membership_id = :mid AND stokvel_id = :sid',
                [
                    'name' => trim((string) ($payload['full_name'] ?? '')),
                    'idn' => $idn,
                    'rel' => trim((string) ($payload['relationship'] ?? '')),
                    'share' => (float) ($payload['share_percent'] ?? 100),
                    'phone' => $this->nullableString($payload['phone'] ?? null),
                    'email' => $this->nullableString($payload['email'] ?? null),
                    'primary' => !empty($payload['is_primary']) ? 1 : 0,
                    'ua' => $now,
                    'id' => $beneficiaryId,
                    'mid' => $membershipId,
                    'sid' => $stokvelId,
                ]
            );
            return;
        }

        $this->db->exec(
            'UPDATE beneficiaries SET
                full_name = :name, relationship = :rel, share_percent = :share,
                phone = :phone, email = :email, is_primary = :primary, updated_at = :ua
             WHERE id = :id AND membership_id = :mid AND stokvel_id = :sid',
            [
                'name' => trim((string) ($payload['full_name'] ?? '')),
                'rel' => trim((string) ($payload['relationship'] ?? '')),
                'share' => (float) ($payload['share_percent'] ?? 100),
                'phone' => $this->nullableString($payload['phone'] ?? null),
                'email' => $this->nullableString($payload['email'] ?? null),
                'primary' => !empty($payload['is_primary']) ? 1 : 0,
                'ua' => $now,
                'id' => $beneficiaryId,
                'mid' => $membershipId,
                'sid' => $stokvelId,
            ]
        );
    }

    /** @param array<string, mixed> $payload */
    private function applyBeneficiaryDelete(int $membershipId, int $stokvelId, array $payload): void
    {
        $beneficiaryId = (int) ($payload['beneficiary_id'] ?? 0);
        if ($beneficiaryId <= 0) {
            throw new \RuntimeException('Beneficiary id required.');
        }

        $this->db->exec(
            'DELETE FROM beneficiaries WHERE id = :id AND membership_id = :mid AND stokvel_id = :sid',
            ['id' => $beneficiaryId, 'mid' => $membershipId, 'sid' => $stokvelId]
        );
    }

    private function notifyMember(int $stokvelId, int $membershipId, string $changeType, string $now): void
    {
        try {
            $member = $this->db->fetch(
                'SELECT user_id FROM memberships WHERE id = :id AND stokvel_id = :sid LIMIT 1',
                ['id' => $membershipId, 'sid' => $stokvelId]
            );
            if (!$member) {
                return;
            }
            $label = str_replace('_', ' ', $changeType);
            $this->db->insert(
                'INSERT INTO notifications
                    (stokvel_id, user_id, channel, notification_type, title, body, status, created_at, updated_at)
                 VALUES
                    (:sid, :uid, :ch, :type, :title, :body, :st, :ca, :ua)',
                [
                    'sid' => $stokvelId,
                    'uid' => (int) $member['user_id'],
                    'ch' => 'in_app',
                    'type' => 'member_change',
                    'title' => 'Profile change needs your approval',
                    'body' => 'Please review a pending ' . $label . ' in Change requests.',
                    'st' => 'unread',
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
        } catch (\Throwable) {
            // Notification schema variants should not block the change request.
        }
    }

    private function createCommitteeApprovalRequest(int $stokvelId, int $changeId, int $requestedByUserId, string $now): void
    {
        try {
            $this->db->insert(
                'INSERT INTO approval_requests
                    (stokvel_id, request_type, reference_type, reference_id, status,
                     requested_by_user_id, requested_at, created_at, updated_at)
                 VALUES
                    (:sid, :rtype, :reftype, :refid, :status, :by, :ra, :ca, :ua)',
                [
                    'sid' => $stokvelId,
                    'rtype' => 'member_change',
                    'reftype' => 'member_change_request',
                    'refid' => $changeId,
                    'status' => 'pending',
                    'by' => $requestedByUserId,
                    'ra' => $now,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
        } catch (\Throwable) {
            // Optional visibility for committee; member approval is authoritative.
        }
    }

    private function finalizeCommitteeVisibility(int $stokvelId, int $changeId, string $status, string $now): void
    {
        try {
            $this->db->exec(
                "UPDATE approval_requests
                 SET status = :st, decided_at = :da, decision_notes = :notes, updated_at = :ua
                 WHERE stokvel_id = :sid AND reference_type = 'member_change_request'
                   AND reference_id = :refid AND status = 'pending'",
                [
                    'st' => $status === 'approved' ? 'approved' : 'rejected',
                    'da' => $now,
                    'notes' => 'Decided by member',
                    'ua' => $now,
                    'sid' => $stokvelId,
                    'refid' => $changeId,
                ]
            );
        } catch (\Throwable) {
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));
        return $s === '' ? null : $s;
    }
}
