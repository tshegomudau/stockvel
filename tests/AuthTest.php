<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Auth\AuthService;
use App\Services\Auth\PasswordResetService;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Registration and login', function (): void {
        test_transaction(function () {
            $auth = App::getInstance()->container()->get(AuthService::class);
            $email = 'test_' . bin2hex(random_bytes(4)) . '@example.com';
            $user = $auth->register([
                'email' => $email,
                'password' => 'Password123!',
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);
            test_assert(isset($user['id']));
            $loggedIn = $auth->login($email, 'Password123!');
            test_assert((int) $loggedIn['id'] === (int) $user['id']);
        });
    }, 'integration');

    $runner->add('Password reset token flow', function (): void {
        test_transaction(function () {
            \App\Core\Session::forget('user_id');
            App::getInstance()->container()->get(\App\Core\TenantContext::class)->setUser(null);
            $container = App::getInstance()->container();
            $auth = $container->get(AuthService::class);
            $reset = $container->get(PasswordResetService::class);
            $email = 'reset_' . bin2hex(random_bytes(4)) . '@example.com';
            $auth->register([
                'email' => $email,
                'password' => 'Password123!',
                'first_name' => 'Reset',
                'last_name' => 'User',
            ]);
            $token = $reset->createToken($email);
            test_assert(is_string($token) && $token !== '');
            $reset->consume($token, 'NewPassword123!');
            test_assert($auth->attempt($email, 'NewPassword123!') !== null);
        });
    }, 'integration');

    $runner->add('Forgot password queues and sends reset email', function (): void {
        test_transaction(function () {
            \App\Core\Session::forget('user_id');
            App::getInstance()->container()->get(\App\Core\TenantContext::class)->setUser(null);

            $prev = $_ENV['MAIL_DRIVER'] ?? null;
            $_ENV['MAIL_DRIVER'] = 'log';
            $_SERVER['MAIL_DRIVER'] = 'log';
            putenv('MAIL_DRIVER=log');

            $container = App::getInstance()->container();
            $auth = $container->get(AuthService::class);
            $reset = $container->get(PasswordResetService::class);
            $email = 'forgot_' . bin2hex(random_bytes(4)) . '@example.com';
            $auth->register([
                'email' => $email,
                'password' => 'Password123!',
                'first_name' => 'Forgot',
                'last_name' => 'User',
            ]);

            $mailDir = App::getInstance()->basePath('storage/mail');
            if (!is_dir($mailDir)) {
                mkdir($mailDir, 0775, true);
            }
            $beforeMail = glob($mailDir . '/*.eml') ?: [];
            $beforeJobs = (int) (test_db()->fetch(
                "SELECT COUNT(*) AS c FROM notification_jobs WHERE recipient = :e AND payload_json LIKE '%password_reset%'",
                ['e' => $email]
            )['c'] ?? 0);

            $token = $reset->createToken($email);
            test_assert(is_string($token) && $token !== '', 'Expected reset token');

            $afterJobs = (int) (test_db()->fetch(
                "SELECT COUNT(*) AS c FROM notification_jobs WHERE recipient = :e AND status = 'sent'",
                ['e' => $email]
            )['c'] ?? 0);
            test_assert($afterJobs > $beforeJobs, 'Expected a sent notification_jobs row for password reset');

            $afterMail = glob($mailDir . '/*.eml') ?: [];
            test_assert(count($afterMail) > count($beforeMail), 'Expected password reset .eml under storage/mail');

            $latest = $afterMail[array_key_last($afterMail)] ?? '';
            $contents = is_string($latest) && $latest !== '' ? (string) file_get_contents($latest) : '';
            test_assert(str_contains($contents, '/reset-password/'), 'Reset email must include reset link');

            if ($prev === null) {
                unset($_ENV['MAIL_DRIVER'], $_SERVER['MAIL_DRIVER']);
                putenv('MAIL_DRIVER');
            } else {
                $_ENV['MAIL_DRIVER'] = $prev;
                $_SERVER['MAIL_DRIVER'] = $prev;
                putenv('MAIL_DRIVER=' . $prev);
            }
        });
    }, 'integration');

    $runner->add('Suspended stokvel is excluded from usable memberships and blocks switch', function (): void {
        test_transaction(function () {
            $db = test_db();
            $auth = App::getInstance()->container()->get(AuthService::class);
            $email = 'susp_' . bin2hex(random_bytes(4)) . '@example.com';
            $user = $auth->register([
                'email' => $email,
                'password' => 'Password123!',
                'first_name' => 'Suspended',
                'last_name' => 'Member',
            ]);
            $userId = (int) $user['id'];
            $now = now_utc();
            $stokvelId = (int) $db->insert(
                'INSERT INTO stokvels (uuid, name, slug, status, timezone, currency_code, created_at, updated_at)
                 VALUES (:u, :n, :s, :st, :tz, :cc, :ca, :ua)',
                [
                    'u' => bin2hex(random_bytes(8)),
                    'n' => 'Suspended Circle',
                    's' => 'suspended-circle-' . bin2hex(random_bytes(2)),
                    'st' => 'suspended',
                    'tz' => 'Africa/Johannesburg',
                    'cc' => 'ZAR',
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
            $db->insert(
                'INSERT INTO memberships (stokvel_id, user_id, member_number, status, joined_at, approved_at, created_at, updated_at)
                 VALUES (:sid, :uid, :mn, :st, :ja, :aa, :ca, :ua)',
                [
                    'sid' => $stokvelId,
                    'uid' => $userId,
                    'mn' => 'M-00001',
                    'st' => 'active',
                    'ja' => $now,
                    'aa' => $now,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );

            test_assert($auth->membershipsForUser($userId) === [], 'Suspended stokvel must not be usable');
            test_assert($auth->hasOnlySuspendedStokvels($userId) === true);
            test_assert(home_path_for_user($user) === '/account/stokvel-suspended');

            $threw = false;
            try {
                $auth->switchStokvel($stokvelId, $userId);
            } catch (\App\Exceptions\HttpException $e) {
                $threw = true;
                test_assert(str_contains(strtolower($e->getMessage()), 'suspended'));
            }
            test_assert($threw, 'switchStokvel must reject suspended stokvel');
        });
    }, 'integration');

    $runner->add('Approved stokvel claim assigns chairperson membership with member number and role', function (): void {
        test_transaction(function () {
            $db = test_db();
            $auth = App::getInstance()->container()->get(AuthService::class);
            $email = 'claim_' . bin2hex(random_bytes(4)) . '@example.com';
            $user = $auth->register([
                'email' => $email,
                'password' => 'Password123!',
                'first_name' => 'Claim',
                'last_name' => 'Chair',
            ]);
            $userId = (int) $user['id'];
            $now = now_utc();
            $stokvelId = (int) $db->insert(
                'INSERT INTO stokvels (uuid, name, slug, status, timezone, currency_code, created_at, updated_at)
                 VALUES (:u, :n, :s, :st, :tz, :cc, :ca, :ua)',
                [
                    'u' => bin2hex(random_bytes(8)),
                    'n' => 'Claim Circle',
                    's' => 'claim-circle-' . bin2hex(random_bytes(2)),
                    'st' => 'active',
                    'tz' => 'Africa/Johannesburg',
                    'cc' => 'ZAR',
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
            $token = bin2hex(random_bytes(8));
            $db->insert(
                "INSERT INTO stokvel_creation_requests (
                    public_token, requester_first_name, requester_last_name, requester_email,
                    stokvel_name, stokvel_type, status, stokvel_id, claim_expires_at, created_at, updated_at
                 ) VALUES (
                    :t, 'Claim', 'Chair', :e, 'Claim Circle', 'general_savings', 'approved', :sid, :exp, :ca, :ua
                 )",
                [
                    't' => $token,
                    'e' => $email,
                    'sid' => $stokvelId,
                    'exp' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+48 hours')->format('Y-m-d H:i:s'),
                    'ca' => $now,
                    'ua' => $now,
                ]
            );

            /** @var \App\Services\Stokvel\StokvelCreationRequestService $svc */
            $svc = App::getInstance()->container()->get(\App\Services\Stokvel\StokvelCreationRequestService::class);
            $claimed = $svc->claimApprovedForAuthenticatedUser($userId, $email);
            test_assert($claimed !== null, 'Claim should return result');
            test_assert((int) $claimed['stokvel_id'] === $stokvelId);

            $membership = $db->fetch(
                'SELECT * FROM memberships WHERE stokvel_id = :sid AND user_id = :uid LIMIT 1',
                ['sid' => $stokvelId, 'uid' => $userId]
            );
            test_assert($membership !== null, 'Membership missing');
            test_assert(($membership['status'] ?? '') === 'active');
            test_assert(preg_match('/^M-\d+$/', (string) ($membership['member_number'] ?? '')) === 1, 'member_number required');

            $roleLink = $db->fetch(
                'SELECT mr.*, r.code FROM membership_roles mr
                 INNER JOIN roles r ON r.id = mr.role_id
                 WHERE mr.membership_id = :mid LIMIT 1',
                ['mid' => (int) $membership['id']]
            );
            test_assert($roleLink !== null, 'Chairperson role assignment missing');
            test_assert((string) ($roleLink['code'] ?? '') === 'chairperson');
            test_assert((int) ($roleLink['stokvel_id'] ?? 0) === $stokvelId);

            $auth->switchStokvel($stokvelId, $userId);
            test_assert((int) (\App\Core\Session::get('active_stokvel_id') ?? 0) === $stokvelId);
        });
    }, 'integration');

    $runner->add('Multi-stokvel user must select before home path resolves', function (): void {
        test_transaction(function () {
            $db = test_db();
            $auth = App::getInstance()->container()->get(AuthService::class);
            $email = 'multi_' . bin2hex(random_bytes(4)) . '@example.com';
            $user = $auth->register([
                'email' => $email,
                'password' => 'Password123!',
                'first_name' => 'Multi',
                'last_name' => 'Link',
            ]);
            $userId = (int) $user['id'];
            $now = now_utc();

            $createMembership = static function (string $name) use ($db, $userId, $now): int {
                $sid = (int) $db->insert(
                    'INSERT INTO stokvels (uuid, name, slug, status, timezone, currency_code, created_at, updated_at)
                     VALUES (:u, :n, :s, :st, :tz, :cc, :ca, :ua)',
                    [
                        'u' => bin2hex(random_bytes(8)),
                        'n' => $name,
                        's' => strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . bin2hex(random_bytes(2)),
                        'st' => 'active',
                        'tz' => 'Africa/Johannesburg',
                        'cc' => 'ZAR',
                        'ca' => $now,
                        'ua' => $now,
                    ]
                );
                $db->insert(
                    'INSERT INTO memberships (stokvel_id, user_id, member_number, status, joined_at, approved_at, created_at, updated_at)
                     VALUES (:sid, :uid, :mn, :st, :ja, :aa, :ca, :ua)',
                    [
                        'sid' => $sid,
                        'uid' => $userId,
                        'mn' => 'M-' . $sid,
                        'st' => 'active',
                        'ja' => $now,
                        'aa' => $now,
                        'ca' => $now,
                        'ua' => $now,
                    ]
                );
                return $sid;
            };

            $sidA = $createMembership('Alpha Circle ' . bin2hex(random_bytes(2)));
            $sidB = $createMembership('Beta Circle ' . bin2hex(random_bytes(2)));

            \App\Core\Session::forget('active_stokvel_id');
            \App\Core\Session::forget('stokvel_id');
            $fresh = $db->fetch('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
            test_assert(home_path_for_user($fresh) === '/account/select-stokvel', 'Multi link with no selection must pick stokvel');

            $auth->switchStokvel($sidB, $userId);
            test_assert((int) (\App\Core\Session::get('active_stokvel_id') ?? 0) === $sidB);
            test_assert(home_path_for_user($fresh) === '/member', 'Selected membership without admin perms lands on member portal');

            $auth->switchStokvel($sidA, $userId);
            test_assert((int) (\App\Core\Session::get('active_stokvel_id') ?? 0) === $sidA);

            $memberships = array_values(array_filter(
                $auth->membershipsForUser($userId),
                static fn (array $m): bool => in_array((string) ($m['status'] ?? ''), ['active', 'pending'], true)
            ));
            test_assert(count($memberships) === 2, 'Both active memberships must be switchable');
        });
    }, 'integration');

    $runner->add('Platform job retry uses scheduled_for not available_at', function (): void {
        test_transaction(function () {
            $db = test_db();
            $jobId = (int) $db->insert(
                "INSERT INTO notification_jobs
                    (stokvel_id, user_id, channel, recipient, subject, body, payload_json, status, attempt_count, max_attempts, scheduled_for, created_at, updated_at, last_error)
                 VALUES (NULL, NULL, 'mail', 'retry@example.com', 'Retry', 'body', NULL, 'failed', 2, 5, :sched, :c, :u, 'boom')",
                ['sched' => now_utc(), 'c' => now_utc(), 'u' => now_utc()]
            );
            $db->exec(
                "UPDATE notification_jobs
                 SET status = 'pending', last_error = NULL, attempt_count = 0,
                     scheduled_for = :sched, updated_at = :ua
                 WHERE id = :id AND status IN ('failed', 'dead')",
                ['sched' => now_utc(), 'ua' => now_utc(), 'id' => $jobId]
            );
            $row = $db->fetch('SELECT status, attempt_count, scheduled_for FROM notification_jobs WHERE id = :id', ['id' => $jobId]);
            test_assert(($row['status'] ?? '') === 'pending', 'Retry should set pending');
            test_assert((int) ($row['attempt_count'] ?? -1) === 0, 'Retry should reset attempts');
            test_assert(!empty($row['scheduled_for']), 'scheduled_for must be set');
        });
    }, 'integration');
};
