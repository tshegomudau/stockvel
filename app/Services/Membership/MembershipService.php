<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Core\Database;
use App\Core\TenantContext as CoreTenantContext;
use App\Exceptions\HttpException;
use App\Repositories\MembershipRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;

final class MembershipService
{
    /** @var string[] */
    private const FINANCIAL_STATUSES = ['active', 'suspended', 'resigned', 'archived'];

    public function __construct(
        private readonly Database $db,
        private readonly MembershipRepository $memberships,
        private readonly UserRepository $users,
        private readonly InvitationService $invitations,
        private readonly CoreTenantContext $tenant,
        private readonly AuditService $audit
    ) {
    }

    public function invite(string $email, ?string $mobile, ?int $roleId, int $invitedByUserId): array
    {
        return $this->invitations->create($email, $mobile, $roleId, $invitedByUserId);
    }

    public function accept(string $token, int $userId): array
    {
        $invitation = $this->invitations->findByToken($token);
        if (!$invitation || ($invitation['status'] ?? '') !== 'pending') {
            throw new HttpException(404, 'Invitation not found.');
        }
        if ($this->invitations->isExpired($invitation)) {
            throw new HttpException(410, 'Invitation expired.');
        }

        $existing = $this->memberships->findForUser($userId, (int) $invitation['stokvel_id']);
        if ($existing) {
            throw new HttpException(422, 'You are already linked to this stokvel.');
        }

        return $this->db->transaction(function () use ($invitation, $userId) {
            $membershipId = $this->memberships->insert([
                'user_id' => $userId,
                'member_number' => $this->memberships->nextMemberNumber(),
                'status' => 'active',
                'joined_at' => now_utc(),
                'approved_at' => now_utc(),
            ]);

            if (!empty($invitation['role_id'])) {
                $this->assignRole((int) $membershipId, (int) $invitation['role_id'], (int) $invitation['invited_by_user_id']);
            }

            $this->transition($membershipId, null, 'active', (int) $invitation['invited_by_user_id'], 'Invitation accepted');
            $this->memberships->updateInvitation((int) $invitation['id'], [
                'status' => 'accepted',
                'accepted_at' => now_utc(),
            ]);

            $membership = $this->memberships->findById($membershipId);
            $this->audit->log('membership.accepted', 'membership', $membershipId, metadata: [
                'invitation_id' => (int) $invitation['id'],
            ]);

            return $membership ?? [];
        });
    }

    public function reject(string $token, int $userId): void
    {
        $invitation = $this->invitations->findByToken($token);
        if (!$invitation) {
            throw new HttpException(404, 'Invitation not found.');
        }

        $this->memberships->updateInvitation((int) $invitation['id'], [
            'status' => 'rejected',
            'rejected_at' => now_utc(),
        ]);

        $this->audit->log('membership.invitation_rejected', 'invitation', (int) $invitation['id'], metadata: [
            'user_id' => $userId,
        ]);
    }

    public function apply(int $userId, ?string $message = null): array
    {
        $membershipId = $this->memberships->insert([
            'user_id' => $userId,
            'member_number' => $this->memberships->nextMemberNumber(),
            'status' => 'pending',
            'notes' => $message,
        ]);
        $this->transition($membershipId, null, 'pending', $userId, 'Application submitted');
        return $this->memberships->findById($membershipId) ?? [];
    }

    public function approve(int $membershipId, int $approvedByUserId): array
    {
        $membership = $this->memberships->findOrFail($membershipId);
        $this->transition($membershipId, (string) $membership['status'], 'active', $approvedByUserId, 'Approved');
        $this->memberships->update($membershipId, [
            'status' => 'active',
            'approved_at' => now_utc(),
            'joined_at' => $membership['joined_at'] ?? now_utc(),
        ]);
        return $this->memberships->findById($membershipId) ?? [];
    }

    public function suspend(int $membershipId, int $actorUserId, string $reason): array
    {
        $membership = $this->memberships->findOrFail($membershipId);
        $this->transition($membershipId, (string) $membership['status'], 'suspended', $actorUserId, $reason);
        $this->memberships->update($membershipId, [
            'status' => 'suspended',
            'suspended_at' => now_utc(),
            'suspension_reason' => $reason,
        ]);
        return $this->memberships->findById($membershipId) ?? [];
    }

    public function reinstate(int $membershipId, int $actorUserId): array
    {
        $membership = $this->memberships->findOrFail($membershipId);
        $this->transition($membershipId, (string) $membership['status'], 'active', $actorUserId, 'Reinstated');
        $this->memberships->update($membershipId, [
            'status' => 'active',
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);
        return $this->memberships->findById($membershipId) ?? [];
    }

    public function resign(int $membershipId, int $userId, ?string $reason = null): array
    {
        $membership = $this->memberships->findOrFail($membershipId);
        if ((int) $membership['user_id'] !== $userId) {
            throw new HttpException(403, 'You can only resign your own membership.');
        }
        $this->transition($membershipId, (string) $membership['status'], 'resigned', $userId, $reason ?? 'Resigned');
        $this->memberships->update($membershipId, [
            'status' => 'resigned',
            'resigned_at' => now_utc(),
        ]);
        return $this->memberships->findById($membershipId) ?? [];
    }

    public function archive(int $membershipId, int $actorUserId, ?string $reason = null): array
    {
        $membership = $this->memberships->findOrFail($membershipId);
        if (!in_array((string) $membership['status'], self::FINANCIAL_STATUSES, true)) {
            throw new HttpException(422, 'Only members with financial history can be archived.');
        }
        $this->transition($membershipId, (string) $membership['status'], 'archived', $actorUserId, $reason ?? 'Archived');
        $this->memberships->update($membershipId, [
            'status' => 'archived',
        ]);
        return $this->memberships->findById($membershipId) ?? [];
    }

    public function statusHistory(int $membershipId): array
    {
        return $this->memberships->statusHistory($membershipId);
    }

    private function transition(int $membershipId, ?string $from, string $to, int $changedBy, string $reason): void
    {
        $this->memberships->insertStatusHistory([
            'membership_id' => $membershipId,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by_user_id' => $changedBy,
            'reason' => $reason,
        ]);
        $this->audit->log('membership.status_changed', 'membership', $membershipId, metadata: [
            'from' => $from,
            'to' => $to,
            'reason' => $reason,
        ]);
    }

    private function assignRole(int $membershipId, int $roleId, int $assignedBy): void
    {
        try {
            $this->db->insert(
                'INSERT INTO membership_roles (stokvel_id, membership_id, role_id, assigned_by_user_id, assigned_at, created_at)
                 VALUES (:stokvel_id, :membership_id, :role_id, :assigned_by_user_id, :assigned_at, :created_at)',
                [
                    'stokvel_id' => $this->tenant->requireStokvelId(),
                    'membership_id' => $membershipId,
                    'role_id' => $roleId,
                    'assigned_by_user_id' => $assignedBy,
                    'assigned_at' => now_utc(),
                    'created_at' => now_utc(),
                ]
            );
        } catch (\Throwable) {
        }
    }
}
