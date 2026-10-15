<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Migrator;

$basePath = dirname(__DIR__);
$_ENV['APP_ENV'] = 'testing';

require $basePath . '/bootstrap/autoload.php';
\App\Core\Env::load($basePath . '/.env');

$app = require $basePath . '/bootstrap/app.php';
$db = $app->container()->get(Database::class);

static $schemaReady = false;
if (!$schemaReady) {
    $m = new Migrator($db->pdo(), $basePath . '/database/migrations');
    try {
        $m->migrate();
    } catch (\Throwable) {
        // Already migrated.
    }
    if (!$db->fetch('SELECT id FROM stokvels LIMIT 1')) {
        (new \App\Core\SeederRunner($db->pdo()))->runDemo();
    }
    $schemaReady = true;
}

function test_db(): Database
{
    return \App\Core\App::getInstance()->container()->get(Database::class);
}

function test_transaction(callable $callback): mixed
{
    $db = test_db();
    $db->beginTransaction();
    try {
        $result = $callback($db);
        $db->rollBack();
        return $result;
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

function test_assert(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}
