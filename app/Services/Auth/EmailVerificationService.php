<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Database;
use App\Exceptions\HttpException;
use App\Repositories\UserRepository;
use App\Services\AuditService;

final class EmailVerificationService
{
    public function __construct(
        private readonly Database $db,
        private readonly UserRepository $users,
        private readonly AuditService $audit
    ) {
    }

    public function createToken(int $userId): string
    {
        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $hours = (int) env('EMAIL_VERIFICATION_TTL_HOURS', 48);
        $expires = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify("+{$hours} hours")
            ->format('Y-m-d H:i:s');

        $this->db->exec(
            'UPDATE email_verification_tokens SET verified_at = :verified_at
             WHERE user_id = :user_id AND verified_at IS NULL',
            ['verified_at' => now_utc(), 'user_id' => $userId]
        );

        $this->db->insert(
            'INSERT INTO email_verification_tokens (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, :expires_at, :created_at)',
            [
                'user_id' => $userId,
                'token_hash' => $hash,
                'expires_at' => $expires,
                'created_at' => now_utc(),
            ]
        );

        return $plain;
    }

    public function resend(int $userId): void
    {
        $this->createToken($userId);
    }

    public function verify(string $plainToken): bool
    {
        try {
            $this->verifyOrFail($plainToken);
            return true;
        } catch (HttpException) {
            return false;
        }
    }

    public function verifyOrFail(string $plainToken): void
    {
        $hash = hash('sha256', $plainToken);
        $row = $this->db->fetch(
            'SELECT * FROM email_verification_tokens
             WHERE token_hash = :token_hash AND verified_at IS NULL
             ORDER BY id DESC LIMIT 1',
            ['token_hash' => $hash]
        );

        if (!$row || strtotime((string) $row['expires_at']) < time()) {
            throw new HttpException(400, 'Invalid or expired verification link.');
        }

        $userId = (int) $row['user_id'];
        $this->users->markEmailVerified($userId);
        $this->db->exec(
            'UPDATE email_verification_tokens SET verified_at = :verified_at WHERE id = :id',
            ['verified_at' => now_utc(), 'id' => (int) $row['id']]
        );

        $this->audit->log('user.email_verified', 'user', $userId, metadata: ['user_id' => $userId]);
    }

    public function isVerified(array $user): bool
    {
        return !empty($user['email_verified_at']);
    }
}
