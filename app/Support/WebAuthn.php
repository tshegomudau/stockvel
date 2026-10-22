<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Pragmatic WebAuthn helpers (no Composer deps).
 *
 * Registration expects the browser to send SPKI public key bytes from
 * AuthenticatorAttestationResponse.getPublicKey() (modern browsers).
 * Assertion verifies ES256 (-7) and RS256 (-257) via OpenSSL.
 *
 * Limitations:
 * - Attestation statement (fmt/attStmt) is not cryptographically validated;
 *   we bind credentials to the user via session challenge + origin/type checks.
 * - EdDSA (-8) and uncommon algs are not supported.
 * - Sign-count cloning detection is best-effort (stored and compared).
 */
final class WebAuthn
{
    public static function relyingPartyId(): string
    {
        $appUrl = (string) env('APP_URL', '');
        $host = parse_url($appUrl, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return $host;
        }
        return $_SERVER['HTTP_HOST'] ?? 'localhost';
    }

    public static function origin(): string
    {
        $appUrl = rtrim((string) env('APP_URL', ''), '/');
        if ($appUrl !== '') {
            return $appUrl;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return ($https ? 'https' : 'http') . '://' . $host;
    }

    public static function randomChallenge(int $bytes = 32): string
    {
        return self::base64UrlEncode(random_bytes($bytes));
    }

    public static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        $padded = strtr($value, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($padded, true);
        return $raw === false ? '' : $raw;
    }

    /**
     * Convert DER/SPKI public key bytes to PEM.
     */
    public static function spkiToPem(string $spkiDer): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spkiDer), 64, "\n")
            . "-----END PUBLIC KEY-----";
    }

    /**
     * @return array{type:string,challenge:string,origin:string}|null
     */
    public static function parseClientDataJson(string $clientDataJsonRaw): ?array
    {
        $data = json_decode($clientDataJsonRaw, true);
        if (!is_array($data)) {
            return null;
        }
        $type = (string) ($data['type'] ?? '');
        $challenge = (string) ($data['challenge'] ?? '');
        $origin = (string) ($data['origin'] ?? '');
        if ($type === '' || $challenge === '' || $origin === '') {
            return null;
        }
        return ['type' => $type, 'challenge' => $challenge, 'origin' => $origin];
    }

    public static function challengeMatches(string $clientChallengeB64url, string $expectedB64url): bool
    {
        return hash_equals($expectedB64url, $clientChallengeB64url);
    }

    public static function originMatches(string $clientOrigin): bool
    {
        return hash_equals(self::origin(), $clientOrigin);
    }

    /**
     * Verify assertion signature.
     *
     * @param string $pem Public key PEM
     * @param int $alg COSE algorithm (-7 ES256, -257 RS256)
     * @param string $authenticatorData raw bytes
     * @param string $clientDataJson raw bytes
     * @param string $signature raw bytes (WebAuthn form)
     */
    public static function verifyAssertion(
        string $pem,
        int $alg,
        string $authenticatorData,
        string $clientDataJson,
        string $signature
    ): bool {
        $signed = $authenticatorData . hash('sha256', $clientDataJson, true);

        if ($alg === -7) {
            $der = self::p1363ToDer($signature);
            if ($der === null) {
                return false;
            }
            $ok = openssl_verify($signed, $der, $pem, OPENSSL_ALGO_SHA256);
            return $ok === 1;
        }

        if ($alg === -257) {
            $ok = openssl_verify($signed, $signature, $pem, OPENSSL_ALGO_SHA256);
            return $ok === 1;
        }

        return false;
    }

    /**
     * Convert IEEE P1363 (r||s) ECDSA signature to DER.
     */
    public static function p1363ToDer(string $p1363): ?string
    {
        $len = strlen($p1363);
        if ($len === 0 || $len % 2 !== 0) {
            return null;
        }
        $half = intdiv($len, 2);
        $r = self::unsignedToDerInt(substr($p1363, 0, $half));
        $s = self::unsignedToDerInt(substr($p1363, $half));
        return "\x30" . self::derLen(strlen($r) + strlen($s)) . $r . $s;
    }

    private static function unsignedToDerInt(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . self::derLen(strlen($bytes)) . $bytes;
    }

    private static function derLen(int $len): string
    {
        if ($len < 128) {
            return chr($len);
        }
        $bin = ltrim(pack('N', $len), "\x00");
        return chr(0x80 | strlen($bin)) . $bin;
    }

    public static function readSignCount(string $authenticatorData): int
    {
        if (strlen($authenticatorData) < 37) {
            return 0;
        }
        $parts = unpack('Ncount', substr($authenticatorData, 33, 4));
        return (int) ($parts['count'] ?? 0);
    }
}
