<?php

declare(strict_types=1);

namespace App\Support;

/**
 * RFC 6238 TOTP (HMAC-SHA1, 30s step, 6 digits) — pure PHP, no dependencies.
 */
final class Totp
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public const PERIOD = 30;
    public const DIGITS = 6;
    public const WINDOW = 1;

    public static function generateSecret(int $bytes = 20): string
    {
        if ($bytes < 16) {
            $bytes = 16;
        }
        return self::base32Encode(random_bytes($bytes));
    }

    public static function verify(string $secret, string $code, ?int $timestamp = null, int $window = self::WINDOW): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp ??= time();
        $counter = intdiv($timestamp, self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            $expected = self::at($secret, $counter + $i);
            if (hash_equals($expected, $code)) {
                return true;
            }
        }
        return false;
    }

    public static function at(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        if ($key === '') {
            return '000000';
        }

        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $truncated = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        );
        $otp = $truncated % (10 ** self::DIGITS);
        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function otpAuthUri(string $secret, string $accountName, string $issuer = 'stockvel'): string
    {
        $label = rawurlencode($issuer . ':' . $accountName);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/' . $label . '?' . $query;
    }

    public static function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $binary = str_pad($binary, (int) (ceil(strlen($binary) / 5) * 5), '0', STR_PAD_RIGHT);
        $out = '';
        foreach (str_split($binary, 5) as $chunk) {
            $out .= self::BASE32_ALPHABET[bindec($chunk)];
        }
        return $out;
    }

    public static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        if ($secret === '') {
            return '';
        }
        $map = array_flip(str_split(self::BASE32_ALPHABET));
        $binary = '';
        foreach (str_split($secret) as $char) {
            if (!isset($map[$char])) {
                return '';
            }
            $binary .= str_pad(decbin($map[$char]), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($binary, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
