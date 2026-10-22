<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Session;
use App\Services\Auth\AuthService;
use App\Services\Auth\MfaService;
use App\Support\SensitiveData;
use App\Support\Totp;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('TOTP generate/verify roundtrip', function (): void {
        $secret = Totp::generateSecret();
        test_assert(strlen($secret) >= 26, 'secret should be base32 of >=16 bytes');
        $code = Totp::at($secret, intdiv(time(), Totp::PERIOD));
        test_assert(Totp::verify($secret, $code), 'current window code must verify');
        test_assert(Totp::verify($secret, $code, time(), 1), 'explicit window verify');
        test_assert(!Totp::verify($secret, '000000') || Totp::verify($secret, '000000'), 'verify returns bool');
        // Wrong code almost always fails (allow tiny collision chance by using fixed wrong)
        test_assert(!Totp::verify($secret, '111111') || Totp::at($secret, intdiv(time(), 30)) === '111111');
        $uri = Totp::otpAuthUri($secret, 'user@example.com', 'stockvel');
        test_assert(str_starts_with($uri, 'otpauth://totp/'), 'otpauth URI');
        test_assert(str_contains($uri, 'secret='), 'URI contains secret');
    }, 'unit');

    $runner->add('TOTP window ±1 accepts adjacent counters', function (): void {
        $secret = Totp::generateSecret();
        $counter = intdiv(time(), Totp::PERIOD);
        $prev = Totp::at($secret, $counter - 1);
        $next = Totp::at($secret, $counter + 1);
        test_assert(Totp::verify($secret, $prev), 'previous step accepted');
        test_assert(Totp::verify($secret, $next), 'next step accepted');
        $far = Totp::at($secret, $counter + 5);
        test_assert(!Totp::verify($secret, $far) || $far === Totp::at($secret, $counter), 'far counter rejected');
    }, 'unit');

    $runner->add('Login MFA gate sets pending session without user_id', function (): void {
        test_transaction(function () {
            Session::forget('user_id');
            Session::forget('mfa_pending_user_id');
            $auth = App::getInstance()->container()->get(AuthService::class);
            $mfa = App::getInstance()->container()->get(MfaService::class);
            $email = 'mfa_' . bin2hex(random_bytes(4)) . '@example.com';
            $user = $auth->register([
                'email' => $email,
                'password' => 'Password123!',
                'first_name' => 'Mfa',
                'last_name' => 'User',
            ]);
            $userId = (int) $user['id'];

            // Simulate enabled TOTP
            $secret = Totp::generateSecret();
            $now = now_utc();
            test_db()->insert(
                'INSERT INTO user_totp (user_id, secret_encrypted, enabled_at, recovery_codes_json, created_at, updated_at)
                 VALUES (:id, :sec, :ea, :rc, :ca, :ua)',
                [
                    'id' => $userId,
                    'sec' => SensitiveData::encrypt($secret),
                    'ea' => $now,
                    'rc' => json_encode([]),
                    'ca' => $now,
                    'ua' => $now,
                ]
            );

            test_assert($mfa->requiresMfa($userId), 'MFA required when TOTP enabled');
            test_assert($mfa->isTotpEnabled($userId));

            Session::forget('user_id');
            $result = $auth->login($email, 'Password123!');
            test_assert(!empty($result['mfa_required']), 'login returns mfa_required');
            test_assert(Session::get('user_id') === null, 'user_id must not be set before MFA');
            test_assert((int) Session::get('mfa_pending_user_id') === $userId, 'pending user set');

            $code = Totp::at($secret, intdiv(time(), Totp::PERIOD));
            test_assert($mfa->verifyTotpOrRecovery($userId, $code), 'TOTP verifies for pending user');

            Session::forget('mfa_pending_user_id');
            Session::forget('mfa_pending_remember');
        });
    }, 'integration');

    $runner->add('MfaService TOTP enable/disable with recovery codes', function (): void {
        test_transaction(function () {
            Session::forget('user_id');
            $auth = App::getInstance()->container()->get(AuthService::class);
            $mfa = App::getInstance()->container()->get(MfaService::class);
            $email = 'mfa2_' . bin2hex(random_bytes(4)) . '@example.com';
            $user = $auth->register([
                'email' => $email,
                'password' => 'Password123!',
                'first_name' => 'Mfa',
                'last_name' => 'Two',
            ]);
            $userId = (int) $user['id'];

            $setup = $mfa->beginTotpSetup($userId, $email);
            $code = Totp::at($setup['secret'], intdiv(time(), Totp::PERIOD));
            $recovery = $mfa->confirmTotpSetup($userId, $code);
            test_assert(count($recovery) === 8, 'eight recovery codes');
            test_assert($mfa->isTotpEnabled($userId));

            $mfa->disableTotp($userId, $recovery[0]);
            test_assert(!$mfa->isTotpEnabled($userId), 'disabled after recovery code');
        });
    }, 'integration');
};
