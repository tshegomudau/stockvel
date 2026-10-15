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

    $runner->add('Notification retry increments attempts', function (): void {
        test_transaction(function () {
            $db = test_db();
            $sid = (int) ($db->fetch('SELECT id FROM stokvels LIMIT 1')['id'] ?? 0);
            $jobId = (int) $db->insert(
                'INSERT INTO notification_jobs (stokvel_id, channel, recipient, body, status, attempt_count, max_attempts, scheduled_for, created_at, updated_at)
                 VALUES (:sid, :ch, :rec, :body, :st, 0, 3, :sf, :ca, :ua)',
                [
                    'sid' => $sid ?: null,
                    'ch' => 'invalid_channel',
                    'rec' => 'test@example.co.za',
                    'body' => 'retry test',
                    'st' => 'pending',
                    'sf' => now_utc(),
                    'ca' => now_utc(),
                    'ua' => now_utc(),
                ]
            );
            $queue = App::getInstance()->container()->get(NotificationQueueService::class);
            $queue->processDue(5);
            $job = $db->fetch('SELECT attempt_count, status FROM notification_jobs WHERE id = :id', ['id' => $jobId]);
            test_assert($job && (int) $job['attempt_count'] >= 1);
        });
    });
};
