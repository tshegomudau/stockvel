<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Database;
use App\Core\Session;
use App\Support\SensitiveData;
use App\Support\Totp;
use App\Support\WebAuthn;

final class MfaService
{
    private const SESSION_TOTP_SETUP = 'mfa_totp_setup_secret';
    private const SESSION_WEBAUTHN_REG = 'mfa_webauthn_reg_challenge';
    private const SESSION_WEBAUTHN_ASSERT = 'mfa_webauthn_assert_challenge';
    private const RECOVERY_COUNT = 8;

    public function __construct(private readonly Database $db)
    {
    }

    public function isTotpEnabled(int $userId): bool
    {
        $row = $this->db->fetch(
            'SELECT enabled_at FROM user_totp WHERE user_id = :id LIMIT 1',
            ['id' => $userId]
        );
        return $row !== null && !empty($row['enabled_at']);
    }

    public function passkeyCount(int $userId): int
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS c FROM user_webauthn_credentials WHERE user_id = :id',
            ['id' => $userId]
        );
        return (int) ($row['c'] ?? 0);
    }

    public function requiresMfa(int $userId): bool
    {
        return $this->isTotpEnabled($userId) || $this->passkeyCount($userId) > 0;
    }

    /**
     * Begin TOTP enrollment: returns plaintext secret (show once) + otpauth URI.
     *
     * @return array{secret:string,otpauth_uri:string}
     */
    public function beginTotpSetup(int $userId, string $accountName): array
    {
        $secret = Totp::generateSecret();
        Session::set(self::SESSION_TOTP_SETUP, [
            'user_id' => $userId,
            'secret' => $secret,
        ]);
        $issuer = (string) env('APP_NAME', 'stockvel');
        return [
            'secret' => $secret,
            'otpauth_uri' => Totp::otpAuthUri($secret, $accountName, $issuer),
        ];
    }

    /**
     * Confirm TOTP with a code; persists encrypted secret + hashed recovery codes.
     *
     * @return list<string> plaintext recovery codes (show once)
     */
    public function confirmTotpSetup(int $userId, string $code): array
    {
        $setup = Session::get(self::SESSION_TOTP_SETUP);
        if (!is_array($setup) || (int) ($setup['user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('TOTP setup session expired. Start again.');
        }
        $secret = (string) ($setup['secret'] ?? '');
        if ($secret === '' || !Totp::verify($secret, $code)) {
            throw new \RuntimeException('Invalid authenticator code.');
        }

        $recoveryPlain = $this->generateRecoveryCodes();
        $recoveryHashed = array_map(static fn (string $c): string => password_hash($c, PASSWORD_DEFAULT), $recoveryPlain);
        $now = now_utc();
        $enc = SensitiveData::encrypt($secret);
        if ($enc === null) {
            throw new \RuntimeException('Unable to store TOTP secret.');
        }

        $existing = $this->db->fetch('SELECT user_id FROM user_totp WHERE user_id = :id', ['id' => $userId]);
        if ($existing) {
            $this->db->exec(
                'UPDATE user_totp SET secret_encrypted = :sec, enabled_at = :ea,
                    recovery_codes_json = :rc, updated_at = :ua WHERE user_id = :id',
                [
                    'sec' => $enc,
                    'ea' => $now,
                    'rc' => json_encode($recoveryHashed, JSON_THROW_ON_ERROR),
                    'ua' => $now,
                    'id' => $userId,
                ]
            );
        } else {
            $this->db->insert(
                'INSERT INTO user_totp (user_id, secret_encrypted, enabled_at, recovery_codes_json, created_at, updated_at)
                 VALUES (:id, :sec, :ea, :rc, :ca, :ua)',
                [
                    'id' => $userId,
                    'sec' => $enc,
                    'ea' => $now,
                    'rc' => json_encode($recoveryHashed, JSON_THROW_ON_ERROR),
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
        }

        Session::forget(self::SESSION_TOTP_SETUP);
        return $recoveryPlain;
    }

    public function disableTotp(int $userId, string $codeOrRecovery): void
    {
        if (!$this->verifyTotpOrRecovery($userId, $codeOrRecovery)) {
            throw new \RuntimeException('Invalid code. TOTP was not disabled.');
        }
        $this->db->exec('DELETE FROM user_totp WHERE user_id = :id', ['id' => $userId]);
    }

    public function verifyTotpOrRecovery(int $userId, string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        $row = $this->db->fetch(
            'SELECT secret_encrypted, enabled_at, recovery_codes_json FROM user_totp WHERE user_id = :id LIMIT 1',
            ['id' => $userId]
        );
        if ($row === null || empty($row['enabled_at'])) {
            return false;
        }

        $secret = SensitiveData::decrypt($row['secret_encrypted'] ?? null);
        if (is_string($secret) && $secret !== '' && Totp::verify($secret, $code)) {
            return true;
        }

        // Recovery codes: XXXX-XXXX style
        $normalized = strtoupper(preg_replace('/\s+/', '', $code) ?? '');
        $hashes = json_decode((string) ($row['recovery_codes_json'] ?? '[]'), true);
        if (!is_array($hashes)) {
            return false;
        }
        foreach ($hashes as $i => $hash) {
            if (!is_string($hash)) {
                continue;
            }
            if (password_verify($normalized, $hash) || password_verify($code, $hash)) {
                unset($hashes[$i]);
                $this->db->exec(
                    'UPDATE user_totp SET recovery_codes_json = :rc, updated_at = :ua WHERE user_id = :id',
                    [
                        'rc' => json_encode(array_values($hashes), JSON_THROW_ON_ERROR),
                        'ua' => now_utc(),
                        'id' => $userId,
                    ]
                );
                return true;
            }
        }
        return false;
    }

    /** @return list<array{id:int,label:?string,credential_id:string,created_at:string,last_used_at:?string}> */
    public function listPasskeys(int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, label, credential_id, created_at, last_used_at
             FROM user_webauthn_credentials WHERE user_id = :id ORDER BY created_at DESC',
            ['id' => $userId]
        );
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'label' => $r['label'] !== null ? (string) $r['label'] : null,
            'credential_id' => (string) $r['credential_id'],
            'created_at' => (string) $r['created_at'],
            'last_used_at' => $r['last_used_at'] !== null ? (string) $r['last_used_at'] : null,
        ], $rows);
    }

    /**
     * @return array{challenge:string,rp:array{id:string,name:string},user:array{id:string,name:string,displayName:string},pubKeyCredParams:list<array{type:string,alg:int}},timeout:int,attestation:string,authenticatorSelection:array<string,mixed>}
     */
    public function beginPasskeyRegistration(int $userId, string $email, string $displayName): array
    {
        $challenge = WebAuthn::randomChallenge();
        Session::set(self::SESSION_WEBAUTHN_REG, [
            'user_id' => $userId,
            'challenge' => $challenge,
        ]);

        $exclude = [];
        foreach ($this->listPasskeys($userId) as $cred) {
            $exclude[] = [
                'type' => 'public-key',
                'id' => $cred['credential_id'],
            ];
        }

        return [
            'challenge' => $challenge,
            'rp' => [
                'id' => WebAuthn::relyingPartyId(),
                'name' => (string) env('APP_NAME', 'stockvel'),
            ],
            'user' => [
                'id' => WebAuthn::base64UrlEncode(hash('sha256', 'webauthn-user:' . $userId, true)),
                'name' => $email,
                'displayName' => $displayName !== '' ? $displayName : $email,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257],
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'residentKey' => 'preferred',
                'userVerification' => 'preferred',
            ],
            'excludeCredentials' => $exclude,
        ];
    }

    /**
     * Complete registration. Expects JSON from browser including:
     * id, rawId, clientDataJSON, publicKey (base64url SPKI), algorithm, optional label.
     *
     * @param array<string, mixed> $credential
     */
    public function completePasskeyRegistration(int $userId, array $credential): int
    {
        $pending = Session::get(self::SESSION_WEBAUTHN_REG);
        if (!is_array($pending) || (int) ($pending['user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException('Passkey registration challenge expired.');
        }
        $expectedChallenge = (string) ($pending['challenge'] ?? '');

        $clientDataB64 = (string) ($credential['clientDataJSON'] ?? '');
        $clientDataRaw = WebAuthn::base64UrlDecode($clientDataB64);
        $client = WebAuthn::parseClientDataJson($clientDataRaw);
        if ($client === null
            || $client['type'] !== 'webauthn.create'
            || !WebAuthn::challengeMatches($client['challenge'], $expectedChallenge)
            || !WebAuthn::originMatches($client['origin'])
        ) {
            throw new \RuntimeException('Invalid passkey registration data.');
        }

        $credentialId = (string) ($credential['id'] ?? $credential['rawId'] ?? '');
        if ($credentialId === '') {
            throw new \RuntimeException('Missing credential id.');
        }

        $spkiB64 = (string) ($credential['publicKey'] ?? '');
        $spki = WebAuthn::base64UrlDecode($spkiB64);
        if ($spki === '') {
            // Fallback: accept PEM text directly
            $pem = (string) ($credential['publicKeyPem'] ?? '');
            if ($pem === '' || !str_contains($pem, 'PUBLIC KEY')) {
                throw new \RuntimeException('Missing public key. Use a browser that supports getPublicKey().');
            }
        } else {
            $pem = WebAuthn::spkiToPem($spki);
        }

        $alg = (int) ($credential['algorithm'] ?? -7);
        if (!in_array($alg, [-7, -257], true)) {
            throw new \RuntimeException('Unsupported public key algorithm.');
        }

        // Store PEM + algorithm as JSON text for assertion
        $publicKeyPayload = json_encode([
            'pem' => $pem,
            'alg' => $alg,
        ], JSON_THROW_ON_ERROR);

        $label = trim((string) ($credential['label'] ?? 'Passkey'));
        if (mb_strlen($label) > 128) {
            $label = mb_substr($label, 0, 128);
        }
        $now = now_utc();

        $existing = $this->db->fetch(
            'SELECT id FROM user_webauthn_credentials WHERE credential_id = :cid LIMIT 1',
            ['cid' => $credentialId]
        );
        if ($existing) {
            throw new \RuntimeException('This passkey is already registered.');
        }

        $id = $this->db->insert(
            'INSERT INTO user_webauthn_credentials
                (user_id, credential_id, public_key, sign_count, label, created_at, last_used_at)
             VALUES (:uid, :cid, :pk, 0, :label, :ca, NULL)',
            [
                'uid' => $userId,
                'cid' => $credentialId,
                'pk' => $publicKeyPayload,
                'label' => $label !== '' ? $label : 'Passkey',
                'ca' => $now,
            ]
        );

        Session::forget(self::SESSION_WEBAUTHN_REG);
        return (int) $id;
    }

    public function deletePasskey(int $userId, int $credentialRowId): void
    {
        $this->db->exec(
            'DELETE FROM user_webauthn_credentials WHERE id = :id AND user_id = :uid',
            ['id' => $credentialRowId, 'uid' => $userId]
        );
    }

    /**
     * @return array{challenge:string,timeout:int,rpId:string,allowCredentials:list<array{type:string,id:string}>,userVerification:string}
     */
    public function beginPasskeyAssertion(int $userId): array
    {
        $challenge = WebAuthn::randomChallenge();
        Session::set(self::SESSION_WEBAUTHN_ASSERT, [
            'user_id' => $userId,
            'challenge' => $challenge,
        ]);

        $allow = [];
        foreach ($this->listPasskeys($userId) as $cred) {
            $allow[] = [
                'type' => 'public-key',
                'id' => $cred['credential_id'],
            ];
        }

        return [
            'challenge' => $challenge,
            'timeout' => 60000,
            'rpId' => WebAuthn::relyingPartyId(),
            'allowCredentials' => $allow,
            'userVerification' => 'preferred',
        ];
    }

    /**
     * @param array<string, mixed> $assertion
     */
    public function completePasskeyAssertion(int $userId, array $assertion): bool
    {
        $pending = Session::get(self::SESSION_WEBAUTHN_ASSERT);
        if (!is_array($pending) || (int) ($pending['user_id'] ?? 0) !== $userId) {
            return false;
        }
        $expectedChallenge = (string) ($pending['challenge'] ?? '');

        $credentialId = (string) ($assertion['id'] ?? $assertion['rawId'] ?? '');
        $row = $this->db->fetch(
            'SELECT id, public_key, sign_count FROM user_webauthn_credentials
             WHERE user_id = :uid AND credential_id = :cid LIMIT 1',
            ['uid' => $userId, 'cid' => $credentialId]
        );
        if ($row === null) {
            return false;
        }

        $clientDataRaw = WebAuthn::base64UrlDecode((string) ($assertion['clientDataJSON'] ?? ''));
        $authData = WebAuthn::base64UrlDecode((string) ($assertion['authenticatorData'] ?? ''));
        $signature = WebAuthn::base64UrlDecode((string) ($assertion['signature'] ?? ''));
        $client = WebAuthn::parseClientDataJson($clientDataRaw);
        if ($client === null
            || $client['type'] !== 'webauthn.get'
            || !WebAuthn::challengeMatches($client['challenge'], $expectedChallenge)
            || !WebAuthn::originMatches($client['origin'])
            || $authData === ''
            || $signature === ''
        ) {
            return false;
        }

        $pk = json_decode((string) $row['public_key'], true);
        if (!is_array($pk) || empty($pk['pem'])) {
            return false;
        }
        $alg = (int) ($pk['alg'] ?? -7);
        if (!WebAuthn::verifyAssertion((string) $pk['pem'], $alg, $authData, $clientDataRaw, $signature)) {
            return false;
        }

        $newCount = WebAuthn::readSignCount($authData);
        $oldCount = (int) ($row['sign_count'] ?? 0);
        if ($newCount !== 0 && $newCount <= $oldCount) {
            // Possible cloned authenticator — reject
            return false;
        }

        $this->db->exec(
            'UPDATE user_webauthn_credentials SET sign_count = :sc, last_used_at = :lu WHERE id = :id',
            [
                'sc' => max($newCount, $oldCount),
                'lu' => now_utc(),
                'id' => (int) $row['id'],
            ]
        );
        Session::forget(self::SESSION_WEBAUTHN_ASSERT);
        return true;
    }

    /** @return list<string> */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_COUNT; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = substr($raw, 0, 4) . '-' . substr($raw, 4, 4);
        }
        return $codes;
    }
}
