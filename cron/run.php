<?php

declare(strict_types=1);

/**
 * Cron entry — invoke from system crontab:
 * * * * * /usr/bin/php /path/to/stockvel/cron/run.php >> /path/to/stockvel/storage/logs/cron.log 2>&1
 */

$basePath = dirname(__DIR__);
passthru(PHP_BINARY . ' ' . escapeshellarg($basePath . '/bin/console') . ' cron:run', $exitCode);
exit($exitCode);
