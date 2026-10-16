<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Database;

final class RememberTokenService
{
    private const COOKIE_NAME = 'stockvel_remember';

    public function __construct(
        private readonly Database $db
    ) {
    }

    public function create(int $userId): void
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $hash = hash('sha256', $validator);
        $days = (int) env('REMEMBER_ME_DAYS', 30);
        $expires = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify("+{$days} days")
            ->format('Y-m-d H:i:s');

        $this->db->exec('DELETE FROM remember_tokens WHERE user_id = :user_id', ['user_id' => $userId]);

        $this->db->insert(
            'INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at, created_at)
             VALUES (:user_id, :selector, :validator_hash, :expires_at, :created_at)',
            [
                'user_id' => $userId,
                'selector' => $selector,
                'validator_hash' => $hash,
                'expires_at' => $expires,
                'created_at' => now_utc(),
            ]
        );

        $this->setCookie($selector, $validator, $days);
    }

    public function consumeFromCookie(): ?int
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (!is_string($raw) || !str_contains($raw, ':')) {
            return null;
        }

        [$selector, $validator] = explode(':', $raw, 2);
        if ($selector === '' || $validator === '') {
            return null;
        }

        $row = $this->db->fetch(
            'SELECT * FROM remember_tokens WHERE selector = :selector LIMIT 1',
            ['selector' => $selector]
        );
        if (!$row) {
            $this->forgetCookie();
            return null;
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            $this->revokeBySelector($selector);
            return null;
        }

        $stored = (string) ($row['validator_hash'] ?? $row['token_hash'] ?? '');
        if (!hash_equals($stored, hash('sha256', $validator))) {
            $this->revokeBySelector($selector);
            $this->forgetCookie();
            return null;
        }

        $this->create((int) $row['user_id']);

        return (int) $row['user_id'];
    }

    public function revokeForUser(int $userId): void
    {
        $this->db->exec('DELETE FROM remember_tokens WHERE user_id = :user_id', ['user_id' => $userId]);
        $this->forgetCookie();
    }

    public function revokeBySelector(string $selector): void
    {
        $this->db->exec('DELETE FROM remember_tokens WHERE selector = :selector', ['selector' => $selector]);
    }

    private function setCookie(string $selector, string $validator, int $days): void
    {
        if (headers_sent() || PHP_SAPI === 'cli') {
            return;
        }
        $secure = filter_var(env('SESSION_SECURE', false), FILTER_VALIDATE_BOOL);
        $sameSite = (string) env('SESSION_SAME_SITE', 'Lax');
        setcookie(
            self::COOKIE_NAME,
            $selector . ':' . $validator,
            [
                'expires' => time() + ($days * 86400),
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => $sameSite,
            ]
        );
    }

    private function forgetCookie(): void
    {
        if (headers_sent() || PHP_SAPI === 'cli') {
            return;
        }
        $secure = filter_var(env('SESSION_SECURE', false), FILTER_VALIDATE_BOOL);
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => (string) env('SESSION_SAME_SITE', 'Lax'),
        ]);
    }
}
