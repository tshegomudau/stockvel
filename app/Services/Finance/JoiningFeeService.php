<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Support\MoneyMath;

final class JoiningFeeService
{
    public function __construct(
        private readonly Database $db,
        private readonly ChargeService $charges,
    ) {
    }

    public function ensureSettings(int $stokvelId): array
    {
        $row = $this->db->fetch(
            'SELECT * FROM joining_fee_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        );
        if ($row) {
            return $row;
        }
        $fromSettings = $this->db->fetch(
            'SELECT joining_fee_amount, minimum_contribution FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        );
        $amount = MoneyMath::normalize((string) ($fromSettings['joining_fee_amount'] ?? '0'));
        $now = now_utc();
        $this->db->insert(
            'INSERT INTO joining_fee_settings
                (stokvel_id, amount, is_compulsory, is_once_off, charge_on_approval, charge_on_anniversary,
                 effective_from, is_active, created_at, updated_at)
             VALUES
                (:sid, :amt, 1, 1, 1, 0, :ef, 1, :ca, :ua)',
            [
                'sid' => $stokvelId,
                'amt' => $amount,
                'ef' => date('Y-m-d'),
                'ca' => $now,
                'ua' => $now,
            ]
        );
        return $this->db->fetch(
            'SELECT * FROM joining_fee_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        ) ?? [];
    }

    /**
     * Charge joining fee snapshot at approval time (never mutates if settings change later).
     */
    public function chargeOnApproval(int $stokvelId, int $membershipId, int $actorUserId): ?int
    {
        $settings = $this->ensureSettings($stokvelId);
        if (!(int) ($settings['is_active'] ?? 0) || !(int) ($settings['charge_on_approval'] ?? 0)) {
            return null;
        }
        $amount = MoneyMath::normalize((string) ($settings['amount'] ?? '0'));
        if ((float) $amount <= 0) {
            return null;
        }

        $existing = $this->db->fetch(
            'SELECT id FROM member_joining_fees WHERE stokvel_id = :sid AND membership_id = :mid LIMIT 1',
            ['sid' => $stokvelId, 'mid' => $membershipId]
        );
        if ($existing) {
            return (int) $existing['id'];
        }

        $now = now_utc();
        $chargeId = null;
        try {
            // Prefer a fee structure marked joining if present; otherwise create a nullable-fee charge.
            $chargeId = $this->createObligationCharge($stokvelId, $membershipId, $amount, 'Joining fee');
        } catch (\Throwable) {
            $chargeId = null;
        }

        return (int) $this->db->insert(
            'INSERT INTO member_joining_fees
                (stokvel_id, membership_id, amount_charged, amount_paid, status, charged_at, due_date,
                 member_charge_id, created_at, updated_at)
             VALUES
                (:sid, :mid, :amt, 0, :st, :ca, :due, :cid, :c2, :u2)',
            [
                'sid' => $stokvelId,
                'mid' => $membershipId,
                'amt' => $amount,
                'st' => (int) ($settings['is_compulsory'] ?? 1) === 1 ? 'outstanding' : 'outstanding',
                'ca' => $now,
                'due' => date('Y-m-d'),
                'cid' => $chargeId,
                'c2' => $now,
                'u2' => $now,
            ]
        );
    }

    public function statusForMembership(int $stokvelId, int $membershipId): string
    {
        $row = $this->db->fetch(
            'SELECT status, amount_charged, amount_paid FROM member_joining_fees
             WHERE stokvel_id = :sid AND membership_id = :mid
             ORDER BY id DESC LIMIT 1',
            ['sid' => $stokvelId, 'mid' => $membershipId]
        );
        if (!$row) {
            $settings = $this->ensureSettings($stokvelId);
            if ((float) ($settings['amount'] ?? 0) <= 0 || !(int) ($settings['is_active'] ?? 0)) {
                return 'not_applicable';
            }
            return 'not_charged';
        }
        return (string) ($row['status'] ?? 'outstanding');
    }

    public function waive(int $stokvelId, int $joiningFeeId, int $userId, string $reason): void
    {
        $this->db->exec(
            "UPDATE member_joining_fees
             SET status = 'waived', waived_at = :wa, waived_by_user_id = :uid, waived_reason = :reason, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid",
            [
                'wa' => now_utc(),
                'uid' => $userId,
                'reason' => $reason !== '' ? $reason : 'Waived by administrator',
                'ua' => now_utc(),
                'id' => $joiningFeeId,
                'sid' => $stokvelId,
            ]
        );
    }

    private function createObligationCharge(int $stokvelId, int $membershipId, string $amount, string $description): int
    {
        return $this->charges->createManualArrear(
            $stokvelId,
            $membershipId,
            $amount,
            date('Y-m-d'),
            $description,
            date('Y-m')
        );
    }
}
