<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Encrypt sensitive PII (e.g. SA ID numbers) at rest using APP_KEY.
 */
final class SensitiveData
{
    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || trim($plain) === '') {
            return null;
        }
        $key = self::key();
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('Unable to encrypt sensitive data.');
        }
        return base64_encode($iv . $cipher);
    }

    public static function decrypt(?string $payload): ?string
    {
        if ($payload === null || $payload === '') {
            return null;
        }
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 17) {
            return null;
        }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? null : $plain;
    }

    public static function hash(?string $plain): ?string
    {
        if ($plain === null || trim($plain) === '') {
            return null;
        }
        return hash_hmac('sha256', strtolower(trim($plain)), self::key());
    }

    public static function maskId(?string $plain): string
    {
        if ($plain === null || strlen($plain) < 4) {
            return '••••';
        }
        return str_repeat('•', max(0, strlen($plain) - 4)) . substr($plain, -4);
    }

    private static function key(): string
    {
        $appKey = (string) env('APP_KEY', 'stockvel-dev-key');
        return hash('sha256', $appKey, true);
    }
}
