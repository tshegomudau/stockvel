<?php

declare(strict_types=1);

/**
 * Shared-hosting cron entry (no SSH).
 * cPanel → Cron Jobs → “Fetch URL” or wget/curl to:
 *   https://your-domain/cron.php?token=CRON_TOKEN
 */

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

use App\Core\Env;
use App\Services\Cron\CronRunner;
use App\Services\Finance\ChargeService;
use App\Services\Notifications\NotificationQueueService;

Env::load($basePath . '/.env', true);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$expected = (string) ($_ENV['CRON_TOKEN'] ?? '');
$provided = (string) ($_GET['token'] ?? $_POST['token'] ?? '');

if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

try {
    $app = require $basePath . '/bootstrap/app.php';
    $container = $app->container();
    $runner = new CronRunner(
        $container->get(\App\Core\Database::class),
        $container->get(\App\Core\Logger::class),
        $container->get(ChargeService::class),
        $container->get(NotificationQueueService::class),
        (int) env('CRON_LOCK_TTL', 300)
    );
    $runner->runAll();
    echo 'OK ' . gmdate('c') . "\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
