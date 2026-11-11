<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\TestFailureNotifier;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Test failure notifier emails support address on failed suite report', function (): void {
        PlatformSettingsService::clearCache();
        /** @var PlatformSettingsService $settings */
        $settings = App::getInstance()->container()->get(PlatformSettingsService::class);
        $settings->save([
            'support_email' => 'ops-alerts@example.test',
            'test_failure_email_enabled' => true,
        ]);
        PlatformSettingsService::clearCache();

        $prevDriver = $_ENV['MAIL_DRIVER'] ?? null;
        $prevSkip = $_ENV['TEST_FAILURE_SKIP_PRODUCTION_MAIL'] ?? null;
        $_ENV['MAIL_DRIVER'] = 'log';
        $_SERVER['MAIL_DRIVER'] = 'log';
        putenv('MAIL_DRIVER=log');
        $_ENV['TEST_FAILURE_SKIP_PRODUCTION_MAIL'] = 'true';
        $_SERVER['TEST_FAILURE_SKIP_PRODUCTION_MAIL'] = 'true';
        putenv('TEST_FAILURE_SKIP_PRODUCTION_MAIL=true');

        $mailDir = dirname(__DIR__) . '/storage/mail';
        @mkdir($mailDir, 0775, true);
        $before = glob($mailDir . '/*.eml') ?: [];

        try {
            /** @var TestFailureNotifier $notifier */
            $notifier = App::getInstance()->container()->get(TestFailureNotifier::class);
            test_assert($notifier->resolveSupportEmail() === 'ops-alerts@example.test');

            $sent = $notifier->notifyIfNeeded([
                'suite' => 'all',
                'passed' => 10,
                'failed' => 1,
                'blocked' => 0,
                'cases' => [
                    ['name' => 'Example failing case', 'status' => 'fail', 'error' => 'expected true'],
                    ['name' => 'Passing case', 'status' => 'pass'],
                ],
                'database' => 'stockvel_testing',
                'finished_at' => gmdate('c'),
            ]);
            test_assert($sent === true, 'Notifier should send on failures');

            $after = glob($mailDir . '/*.eml') ?: [];
            test_assert(count($after) > count($before), 'Expected a new mail log file');
            rsort($after);
            $newest = $after[0] ?? '';
            $contents = is_file($newest) ? (string) file_get_contents($newest) : '';
            test_assert(str_contains($contents, 'ops-alerts@example.test'), 'Mail should address support email');
            test_assert(str_contains($contents, 'Example failing case'), 'Mail should list failed case');
            test_assert(str_contains($contents, 'Failed: 1') || str_contains($contents, 'test:all failed'), 'Mail should mention failure summary');
        } finally {
            if ($prevDriver === null) {
                unset($_ENV['MAIL_DRIVER'], $_SERVER['MAIL_DRIVER']);
                putenv('MAIL_DRIVER');
            } else {
                $_ENV['MAIL_DRIVER'] = $prevDriver;
                $_SERVER['MAIL_DRIVER'] = $prevDriver;
                putenv('MAIL_DRIVER=' . $prevDriver);
            }
            if ($prevSkip === null) {
                unset($_ENV['TEST_FAILURE_SKIP_PRODUCTION_MAIL'], $_SERVER['TEST_FAILURE_SKIP_PRODUCTION_MAIL']);
                putenv('TEST_FAILURE_SKIP_PRODUCTION_MAIL');
            } else {
                $_ENV['TEST_FAILURE_SKIP_PRODUCTION_MAIL'] = $prevSkip;
                $_SERVER['TEST_FAILURE_SKIP_PRODUCTION_MAIL'] = $prevSkip;
                putenv('TEST_FAILURE_SKIP_PRODUCTION_MAIL=' . $prevSkip);
            }
            PlatformSettingsService::clearCache();
        }
    }, 'integration');

    $runner->add('Test failure notifier skips when disabled or suite is green', function (): void {
        PlatformSettingsService::clearCache();
        /** @var PlatformSettingsService $settings */
        $settings = App::getInstance()->container()->get(PlatformSettingsService::class);
        $settings->save([
            'support_email' => 'ops-alerts@example.test',
            'test_failure_email_enabled' => false,
        ]);
        PlatformSettingsService::clearCache();

        /** @var TestFailureNotifier $notifier */
        $notifier = App::getInstance()->container()->get(TestFailureNotifier::class);
        $skipped = $notifier->notifyIfNeeded([
            'suite' => 'all',
            'passed' => 1,
            'failed' => 1,
            'blocked' => 0,
            'cases' => [['name' => 'x', 'status' => 'fail', 'error' => 'nope']],
        ]);
        test_assert($skipped === false, 'Disabled flag must skip email');

        $settings->save(['test_failure_email_enabled' => true]);
        PlatformSettingsService::clearCache();
        $green = $notifier->notifyIfNeeded([
            'suite' => 'all',
            'passed' => 5,
            'failed' => 0,
            'blocked' => 0,
            'cases' => [],
        ]);
        test_assert($green === false, 'Green suite must not email');
        PlatformSettingsService::clearCache();
    }, 'integration');
};
