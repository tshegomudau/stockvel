<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Exceptions\HttpException;
use App\Repositories\MembershipRepository;
use App\Services\AuditService;
use App\Services\Notifications\NotificationService;

final class InvitationService
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications
    ) {
    }

    public function create(string $email, ?string $mobile, ?int $roleId, int $invitedByUserId, ?string $message = null): array
    {
        $email = strtolower(trim($email));
        if ($email === '' && ($mobile === null || trim($mobile) === '')) {
            throw new HttpException(422, 'Email or mobile is required for an invitation.');
        }

        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $days = (int) env('INVITATION_TTL_DAYS', 14);
        $expires = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify("+{$days} days")
            ->format('Y-m-d H:i:s');

        $id = $this->memberships->createInvitation([
            'email' => $email !== '' ? $email : null,
            'mobile' => $mobile ? trim($mobile) : null,
            'token_hash' => $hash,
            'role_id' => $roleId,
            'status' => 'pending',
            'invited_by_user_id' => $invitedByUserId,
            'message' => $message,
            'expires_at' => $expires,
        ]);

        $this->audit->log('membership.invitation_created', 'invitation', $id, metadata: [
            'email' => $email !== '' ? $email : null,
        ]);

        if ($email !== '') {
            $this->notifications->queueMail(
                $email,
                'You are invited to join a stokvel',
                'Use this link to respond: ' . url('/invitations/' . $plain)
            );
        }

        return [
            'invitation_id' => $id,
            'token' => $plain,
            'expires_at' => $expires,
        ];
    }

    public function findByToken(string $plainToken): ?array
    {
        return $this->memberships->findInvitationByTokenHash(hash('sha256', $plainToken));
    }

    public function isExpired(array $invitation): bool
    {
        return strtotime((string) $invitation['expires_at']) < time();
    }
}
