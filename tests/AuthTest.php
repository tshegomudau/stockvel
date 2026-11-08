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
