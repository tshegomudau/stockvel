<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Cron\CronRunner;
use App\Services\Notifications\NotificationQueueService;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Repeated cron safe (locks)', function (): void {
        $container = App::getInstance()->container();
        $cron = new CronRunner(
            test_db(),
            $container->get(\App\Core\Logger::class),
            $container->get(\App\Services\Finance\ChargeService::class),
            $container->get(NotificationQueueService::class),
            300
        );
        $first = $cron->runTask('test:lock:' . bin2hex(random_bytes(3)), static fn () => 'ok');
        test_assert($first === 'ok');
    });

    $runner->add('Concurrent cron lock skips second runner', function (): void {
        $container = App::getInstance()->container();
        $key = 'test:lock:dup:' . bin2hex(random_bytes(3));
        $cronA = new CronRunner(
            test_db(),
            $container->get(\App\Core\Logger::class),
            $container->get(\App\Services\Finance\ChargeService::class),
            $container->get(NotificationQueueService::class),
            300
        );
        $cronB = new CronRunner(
            test_db(),
            $container->get(\App\Core\Logger::class),
            $container->get(\App\Services\Finance\ChargeService::class),
            $container->get(NotificationQueueService::class),
            300
        );
        $second = null;
        $first = $cronA->runTask($key, static function () use ($cronB, $key, &$second) {
            $second = $cronB->runTask($key, static fn () => 'should-not-run');
            return 'held';
        });
        test_assert($first === 'held');
        test_assert($second === null);
    });

    $runner->add('cleanup:logs and cleanup:email remove aged files only', function (): void {
        $container = App::getInstance()->container();
        $base = App::getInstance()->basePath('storage');
        $logsDir = $base . '/logs';
        $mailDir = $base . '/mail';
        @mkdir($logsDir, 0775, true);
        @mkdir($mailDir, 0775, true);

        $oldLog = $logsDir . '/app-2020-01-01.log';
        $keepLog = $logsDir . '/app-' . date('Y-m-d') . '.log';
        $oldMail = $mailDir . '/2020-01-01_120000_cleanuptest.eml';
        $keepMail = $mailDir . '/' . date('Y-m-d') . '_120000_cleanupkeep.eml';

        file_put_contents($oldLog, "{\"ts\":\"2020-01-01\",\"level\":\"INFO\",\"message\":\"old\"}\n");
        file_put_contents($keepLog, "{\"ts\":\"" . now_utc() . "\",\"level\":\"INFO\",\"message\":\"keep\"}\n", FILE_APPEND);
        file_put_contents($oldMail, "Subject: old\n\nbody\n");
        file_put_contents($keepMail, "Subject: keep\n\nbody\n");
        touch($oldLog, strtotime('2020-01-01 12:00:00'));
        touch($oldMail, strtotime('2020-01-01 12:00:00'));

        $prevLog = $_ENV['LOG_RETENTION_DAYS'] ?? null;
        $prevEmail = $_ENV['EMAIL_LOG_RETENTION_DAYS'] ?? null;
        $_ENV['LOG_RETENTION_DAYS'] = '7';
        $_SERVER['LOG_RETENTION_DAYS'] = '7';
        putenv('LOG_RETENTION_DAYS=7');
        $_ENV['EMAIL_LOG_RETENTION_DAYS'] = '7';
        $_SERVER['EMAIL_LOG_RETENTION_DAYS'] = '7';
        putenv('EMAIL_LOG_RETENTION_DAYS=7');

        try {
            $cron = new CronRunner(
                test_db(),
                $container->get(\App\Core\Logger::class),
                $container->get(\App\Services\Finance\ChargeService::class),
                $container->get(NotificationQueueService::class),
                300
            );
            $logStats = $cron->runCleanupLogs();
            $mailStats = $cron->runCleanupEmail();

            test_assert((int) $logStats['removed'] >= 1, 'Expected at least one aged log removed');
            test_assert((int) $mailStats['removed'] >= 1, 'Expected at least one aged mail log removed');
            test_assert(!is_file($oldLog), 'Old app log should be deleted');
            test_assert(is_file($keepLog), 'Current day log must be kept');
            test_assert(!is_file($oldMail), 'Old .eml should be deleted');
            test_assert(is_file($keepMail), 'Recent .eml must be kept');
        } finally {
            @unlink($oldLog);
            @unlink($keepMail);
            @unlink($oldMail);
            if ($prevLog === null) {
                unset($_ENV['LOG_RETENTION_DAYS'], $_SERVER['LOG_RETENTION_DAYS']);
                putenv('LOG_RETENTION_DAYS');
            } else {
                $_ENV['LOG_RETENTION_DAYS'] = $prevLog;
                $_SERVER['LOG_RETENTION_DAYS'] = $prevLog;
                putenv('LOG_RETENTION_DAYS=' . $prevLog);
            }
            if ($prevEmail === null) {
                unset($_ENV['EMAIL_LOG_RETENTION_DAYS'], $_SERVER['EMAIL_LOG_RETENTION_DAYS']);
                putenv('EMAIL_LOG_RETENTION_DAYS');
            } else {
                $_ENV['EMAIL_LOG_RETENTION_DAYS'] = $prevEmail;
                $_SERVER['EMAIL_LOG_RETENTION_DAYS'] = $prevEmail;
                putenv('EMAIL_LOG_RETENTION_DAYS=' . $prevEmail);
            }
        }
    }, 'integration');

    $runner->add('Notification retry increments attempts', function (): void {
        test_transaction(function () {
            $db = test_db();
            $sid = (int) ($db->fetch('SELECT id FROM stokvels LIMIT 1')['id'] ?? 0);
            $dueAt = (new DateTimeImmutable(now_utc(), new DateTimeZone(app_timezone())))
                ->modify('-1 minute')
                ->format('Y-m-d H:i:s');
            $jobId = (int) $db->insert(
                'INSERT INTO notification_jobs (stokvel_id, channel, recipient, body, status, attempt_count, max_attempts, scheduled_for, created_at, updated_at)
                 VALUES (:sid, :ch, :rec, :body, :st, 0, 3, :sf, :ca, :ua)',
                [
                    'sid' => $sid ?: null,
                    'ch' => 'invalid_channel',
                    'rec' => 'retry_' . bin2hex(random_bytes(3)) . '@example.co.za',
                    'body' => 'retry test',
                    'st' => 'pending',
                    'sf' => $dueAt,
                    'ca' => now_utc(),
                    'ua' => now_utc(),
                ]
            );
            test_assert($jobId > 0, 'Expected notification job insert id');

            $queue = App::getInstance()->container()->get(NotificationQueueService::class);
            // Target this job directly so other due rows cannot starve the assertion.
            $ok = $queue->processJobById($jobId);
            test_assert($ok === false, 'Invalid channel must fail delivery');

            $job = $db->fetch('SELECT attempt_count, status, last_error FROM notification_jobs WHERE id = :id', ['id' => $jobId]);
            test_assert($job !== null, 'Notification job missing after process');
            test_assert((int) $job['attempt_count'] >= 1, 'Failed delivery must increment attempt_count');
            test_assert(in_array((string) $job['status'], ['failed', 'dead'], true), 'Failed delivery must mark job failed/dead');
        });
    });
};
