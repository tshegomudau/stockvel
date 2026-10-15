<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Database;
use App\Exceptions\HttpException;
use App\Repositories\UserRepository;
use App\Services\AuditService;

final class PasswordResetService
{
    public function __construct(
        private readonly Database $db,
        private readonly UserRepository $users,
        private readonly AuditService $audit
    ) {
    }

    public function sendResetLink(string $email): void
    {
        $this->createToken($email);
    }

    public function createToken(string $email): ?string
    {
        $user = $this->users->findByEmail($email);
        if (!$user) {
            return null;
        }

        $plain = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plain);
        $minutes = (int) env('PASSWORD_RESET_TTL_MINUTES', 60);
        $expires = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify("+{$minutes} minutes")
            ->format('Y-m-d H:i:s');

        $this->db->exec(
            'UPDATE password_reset_tokens SET used_at = :used_at
             WHERE user_id = :user_id AND used_at IS NULL',
            ['used_at' => now_utc(), 'user_id' => (int) $user['id']]
        );

        $this->db->insert(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, :expires_at, :created_at)',
            [
                'user_id' => (int) $user['id'],
                'token_hash' => $hash,
                'expires_at' => $expires,
                'created_at' => now_utc(),
            ]
        );

        try {
            $this->audit->log('password_reset.requested', 'user', (int) $user['id'], metadata: [
                'user_id' => (int) $user['id'],
            ]);
        } catch (\Throwable) {
        }

        return $plain;
    }

    public function reset(string $plainToken, string $newPassword): bool
    {
        try {
            $this->consume($plainToken, $newPassword);
            return true;
        } catch (HttpException) {
            return false;
        }
    }

    public function consume(string $plainToken, string $newPassword): void
    {
        $hash = hash('sha256', $plainToken);
        $row = $this->db->fetch(
            'SELECT * FROM password_reset_tokens
             WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > :now
             ORDER BY id DESC LIMIT 1',
            ['token_hash' => $hash, 'now' => now_utc()]
        );

        if (!$row) {
            throw new HttpException(400, 'Invalid or expired password reset token.');
        }

        $userId = (int) $row['user_id'];
        $this->users->updatePassword($userId, AuthService::hashPassword($newPassword));
        $this->db->exec(
            'UPDATE password_reset_tokens SET used_at = :used_at WHERE id = :id',
            ['used_at' => now_utc(), 'id' => (int) $row['id']]
        );

        try {
            $this->audit->log('password_reset.completed', 'user', $userId, metadata: ['user_id' => $userId]);
        } catch (\Throwable) {
        }
    }
}
