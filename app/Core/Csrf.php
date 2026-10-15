<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\CsrfException;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
            Session::set(self::SESSION_KEY . '_time', time());
        }
        return $token;
    }

    public static function validate(?string $token): void
    {
        $sessionToken = Session::get(self::SESSION_KEY);
        $created = (int) Session::get(self::SESSION_KEY . '_time', 0);
        $ttl = (int) env('CSRF_TOKEN_TTL', 7200);

        if (!is_string($sessionToken) || !is_string($token) || $token === '') {
            throw new CsrfException('Invalid CSRF token.');
        }
        if ($created > 0 && (time() - $created) > $ttl) {
            throw new CsrfException('CSRF token expired.');
        }
        if (!hash_equals($sessionToken, $token)) {
            throw new CsrfException('CSRF token mismatch.');
        }
    }
}
