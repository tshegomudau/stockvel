<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Core\Database;
use App\Core\TenantContext as CoreTenantContext;
use App\Exceptions\HttpException;
use App\Repositories\MembershipRepository;
use App\Repositories\UserRepository;
use App\Services\Approvals\ApprovalService;
use App\Services\AuditService;
use App\Services\Finance\JoiningFeeService;
use App\Services\Notifications\NotificationService;

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
        private readonly AuditService $audit,
        private readonly JoiningFeeService $joiningFees,
        private readonly ApprovalService $approvals,
        private readonly NotificationService $notifications,
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

        $stokvelId = (int) $invitation['stokvel_id'];
        if ($stokvelId < 1) {
            throw new HttpException(422, 'Invitation is missing a stokvel.');
        }

        $existing = $this->memberships->findForUser($userId, $stokvelId);
        if ($existing) {
            // Already accepted / linked — treat as success path for the caller when still pending.
            if (($existing['status'] ?? '') === 'pending') {
                return $existing;
            }
            throw new HttpException(422, 'You are already linked to this stokvel.');
        }

        $previousStokvel = $this->tenant->stokvel();
        $previousMembership = $this->tenant->membership();
        $previousPermissions = $this->tenant->permissions();
        $previousModules = $this->tenant->enabledModules();
        // Bind tenant before any repository call that scopes by stokvel.
        $this->tenant->setTenant(['id' => $stokvelId]);

        try {
            return $this->db->transaction(function () use ($invitation, $userId, $stokvelId) {
                // Invitees join as pending — only admins (members.manage) activate membership.
                $membershipId = $this->memberships->insert([
                    'stokvel_id' => $stokvelId,
                    'user_id' => $userId,
                    'member_number' => $this->memberships->nextMemberNumber(),
                    'status' => 'pending',
                    'joined_at' => null,
                    'approved_at' => null,
                    'notes' => 'Awaiting admin approval after invitation acceptance',
                ]);

                if (!empty($invitation['role_id'])) {
                    $this->assignRole((int) $membershipId, (int) $invitation['role_id'], (int) $invitation['invited_by_user_id']);
                }

                $this->transition($membershipId, null, 'pending', (int) $invitation['invited_by_user_id'], 'Invitation accepted — pending admin approval');
                $this->memberships->updateInvitation((int) $invitation['id'], [
                    'status' => 'accepted',
                    'accepted_at' => now_utc(),
                ]);

                // Surface on Approvals inbox for committee / admins.
                $this->approvals->createRequest(
                    $stokvelId,
                    'membership_invite',
                    'membership',
                    $membershipId,
                    $userId
                );

                $this->notifyAdminsOfPendingInvite($stokvelId, $membershipId, $userId);

                $membership = $this->memberships->findById($membershipId);
                $this->audit->log('membership.accepted_pending', 'membership', $membershipId, metadata: [
                    'invitation_id' => (int) $invitation['id'],
                ]);

                return $membership ?? [];
            });
        } finally {
            $this->tenant->setTenant($previousStokvel, $previousMembership, $previousPermissions, $previousModules);
        }
    }

    public function reject(string $token, int $userId): void
    {
        $invitation = $this->invitations->findByToken($token);
        if (!$invitation) {
            throw new HttpException(404, 'Invitation not found.');
        }

        $previousStokvel = $this->tenant->stokvel();
        $previousMembership = $this->tenant->membership();
        $previousPermissions = $this->tenant->permissions();
        $previousModules = $this->tenant->enabledModules();
        $this->tenant->setTenant(['id' => (int) $invitation['stokvel_id']]);

        try {
            $this->memberships->updateInvitation((int) $invitation['id'], [
                'status' => 'rejected',
                'rejected_at' => now_utc(),
            ]);
        } finally {
            $this->tenant->setTenant($previousStokvel, $previousMembership, $previousPermissions, $previousModules);
        }

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
        $stokvelId = (int) ($membership['stokvel_id'] ?? $this->tenant->stokvelId() ?? 0);
        if ($stokvelId > 0) {
            $this->joiningFees->chargeOnApproval($stokvelId, $membershipId, $approvedByUserId);
            $this->closePendingInviteApproval($stokvelId, $membershipId, $approvedByUserId, 'approved');
        }
        $updated = $this->memberships->findById($membershipId) ?? [];
        $this->notifyMemberApproved($updated, $stokvelId);

        return $updated;
    }

    /** @param array<string, mixed> $membership */
    private function notifyMemberApproved(array $membership, int $stokvelId): void
    {
        $userId = (int) ($membership['user_id'] ?? 0);
        if ($userId < 1 || $stokvelId < 1) {
            return;
        }

        $user = $this->users->findById($userId);
        if ($user === null) {
            return;
        }

        $memberName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        if ($memberName === '') {
            $memberName = (string) ($user['email'] ?? 'Member');
        }
        $memberNumber = (string) ($membership['member_number'] ?? '—');
        $stokvelName = (string) ($this->db->fetch(
            'SELECT name FROM stokvels WHERE id = :id LIMIT 1',
            ['id' => $stokvelId]
        )['name'] ?? 'your stokvel');
        $portalUrl = url('/member');

        try {
            $this->notifications->notifyInApp(
                $userId,
                'membership_approved',
                'Membership approved',
                'Your membership of ' . $stokvelName . ' is now active'
                    . ($memberNumber !== '—' ? ' (member ' . $memberNumber . ').' : '.'),
                $stokvelId,
                ['membership_id' => (int) ($membership['id'] ?? 0), 'url' => '/member']
            );
        } catch (\Throwable) {
            // In-app notify must not block approval.
        }

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $subject = 'Your membership of ' . $stokvelName . ' is approved';
        $body = "Hello {$memberName},\n\n"
            . "Your membership of {$stokvelName} has been approved.\n\n"
            . "Member number: {$memberNumber}\n\n"
            . "Open the member portal:\n{$portalUrl}\n";

        try {
            $rendered = app()->container()
                ->get(\App\Services\Notifications\EmailTemplateService::class)
                ->render($stokvelId, 'membership_approved', [
                    'stokvel_name' => $stokvelName,
                    'member_name' => $memberName,
                    'member_number' => $memberNumber,
                    'portal_url' => $portalUrl,
                ]);
            $subject = $rendered['subject'];
            $body = $rendered['body'];
        } catch (\Throwable) {
            // Keep plaintext fallback.
        }

        try {
            $this->notifications->sendMailNow(
                $email,
                $subject,
                $body,
                [
                    'type' => 'membership_approved',
                    'membership_id' => (int) ($membership['id'] ?? 0),
                    'stokvel_id' => $stokvelId,
                ]
            );
        } catch (\Throwable) {
            // Mail failures are audited via notification_jobs; approval must succeed.
        }
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

    private function notifyAdminsOfPendingInvite(int $stokvelId, int $membershipId, int $acceptedByUserId): void
    {
        $user = $this->users->findById($acceptedByUserId);
        $label = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
        if ($label === '') {
            $label = (string) ($user['email'] ?? ('User #' . $acceptedByUserId));
        }

        $admins = $this->db->fetchAll(
            "SELECT DISTINCT m.user_id
             FROM memberships m
             INNER JOIN membership_roles mr ON mr.membership_id = m.id AND mr.stokvel_id = m.stokvel_id
             INNER JOIN roles r ON r.id = mr.role_id
             WHERE m.stokvel_id = :sid
               AND m.status IN ('active', 'suspended')
               AND r.code IN ('chairperson', 'secretary', 'treasurer')",
            ['sid' => $stokvelId]
        );

        foreach ($admins as $admin) {
            $adminId = (int) ($admin['user_id'] ?? 0);
            if ($adminId < 1 || $adminId === $acceptedByUserId) {
                continue;
            }
            try {
                $this->notifications->notifyInApp(
                    $adminId,
                    'membership_invite_pending',
                    'Membership invitation awaiting approval',
                    $label . ' accepted an invitation and is waiting for approval.',
                    $stokvelId,
                    ['membership_id' => $membershipId, 'url' => '/app/approvals']
                );
            } catch (\Throwable) {
                // Notification schema variants must not block acceptance.
            }
        }
    }

    /** Called from Approvals when a membership_invite request is rejected. */
    public function rejectPendingMembership(int $membershipId, int $actorUserId, string $reason): array
    {
        $membership = $this->memberships->findOrFail($membershipId);
        if (($membership['status'] ?? '') !== 'pending') {
            throw new HttpException(422, 'Only pending memberships can be rejected from an invitation.');
        }
        $this->transition($membershipId, (string) $membership['status'], 'rejected', $actorUserId, $reason);
        $this->memberships->update($membershipId, [
            'status' => 'rejected',
            'notes' => $reason !== '' ? $reason : 'Invitation rejected by admin',
        ]);
        $stokvelId = (int) ($membership['stokvel_id'] ?? 0);
        if ($stokvelId > 0) {
            $this->closePendingInviteApproval($stokvelId, $membershipId, $actorUserId, 'rejected', $reason);
        }
        return $this->memberships->findById($membershipId) ?? [];
    }

    private function closePendingInviteApproval(
        int $stokvelId,
        int $membershipId,
        int $actorUserId,
        string $status,
        string $notes = ''
    ): void {
        try {
            $now = now_utc();
            $this->db->exec(
                "UPDATE approval_requests
                 SET status = :st,
                     approved_by_user_id = CASE WHEN :st2 = 'approved' THEN :by ELSE approved_by_user_id END,
                     rejected_by_user_id = CASE WHEN :st3 = 'rejected' THEN :by2 ELSE rejected_by_user_id END,
                     decision_notes = :notes,
                     decided_at = :da,
                     updated_at = :ua
                 WHERE stokvel_id = :sid
                   AND reference_type = 'membership'
                   AND reference_id = :mid
                   AND status = 'pending'",
                [
                    'st' => $status,
                    'st2' => $status,
                    'st3' => $status,
                    'by' => $actorUserId,
                    'by2' => $actorUserId,
                    'notes' => $notes !== '' ? $notes : null,
                    'da' => $now,
                    'ua' => $now,
                    'sid' => $stokvelId,
                    'mid' => $membershipId,
                ]
            );
        } catch (\Throwable) {
            // Older DBs / concurrent approval path — ignore.
        }
    }
}
