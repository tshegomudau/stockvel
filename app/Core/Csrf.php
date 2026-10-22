<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\CsrfException;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';
    private const TIME_KEY = '_csrf_token_time';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        $created = (int) Session::get(self::TIME_KEY, 0);
        $ttl = self::ttl();

        $missing = !is_string($token) || $token === '';
        $expired = $created > 0 && (time() - $created) > $ttl;

        if ($missing || $expired) {
            return self::regenerate();
        }

        return $token;
    }

    public static function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::set(self::SESSION_KEY, $token);
        Session::set(self::TIME_KEY, time());

        return $token;
    }

    public static function validate(?string $token): void
    {
        $sessionToken = Session::get(self::SESSION_KEY);
        $created = (int) Session::get(self::TIME_KEY, 0);
        $ttl = self::ttl();

        if (!is_string($sessionToken) || !is_string($token) || $token === '') {
            self::regenerate();
            throw new CsrfException('Invalid CSRF token.');
        }

        if ($created > 0 && (time() - $created) > $ttl) {
            self::regenerate();
            throw new CsrfException('CSRF token expired.');
        }

        if (!hash_equals($sessionToken, $token)) {
            self::regenerate();
            throw new CsrfException('CSRF token mismatch.');
        }

        // Sliding window: keep active sessions from expiring mid-use.
        Session::set(self::TIME_KEY, time());
    }

    private static function ttl(): int
    {
        $ttl = (int) env('CSRF_TOKEN_TTL', 7200);

        return $ttl > 0 ? $ttl : 7200;
    }
}
