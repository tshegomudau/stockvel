<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Database;
use App\Core\Migrator;
use App\Core\TestingGuard;

$basePath = dirname(__DIR__);
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

require_once $basePath . '/bootstrap/autoload.php';
\App\Core\Env::load($basePath . '/.env', false);
\App\Core\Env::load($basePath . '/.env.testing', true);

$dbName = TestingGuard::currentDatabaseName();
TestingGuard::assertSafeForDestructiveOps($dbName, 'test bootstrap');

try {
    $app = App::getInstance();
} catch (\Throwable) {
    $app = require $basePath . '/bootstrap/app.php';
}

$db = $app->container()->get(Database::class);

$connected = (string) ($db->fetch('SELECT DATABASE() AS d')['d'] ?? '');
if ($connected !== $dbName) {
    throw new RuntimeException("Connected database '{$connected}' does not match configured test database '{$dbName}'.");
}
if (!TestingGuard::isTestDatabaseName($connected)) {
    throw new RuntimeException("Refusing to run tests against non-test database '{$connected}'.");
}

static $schemaReady = false;
if (!$schemaReady) {
    $m = new Migrator($db->pdo(), $basePath . '/database/migrations');
    $m->migrate();
    $hasUsers = (int) ($db->fetch('SELECT COUNT(*) AS c FROM users')['c'] ?? 0);
    if ($hasUsers === 0) {
        require_once $basePath . '/database/seeders/E2ETestSeeder.php';
        (new \Database\Seeders\E2ETestSeeder())->run($db->pdo());
    }
    $schemaReady = true;
}

@mkdir($basePath . '/storage/test-artifacts', 0775, true);
@mkdir($basePath . '/storage/logs', 0775, true);

function test_db(): Database
{
    return App::getInstance()->container()->get(Database::class);
}

function test_transaction(callable $callback): mixed
{
    $db = test_db();
    $db->beginTransaction();
    try {
        $result = $callback($db);
        $db->rollBack();
        // Rolled-back users must not remain in session/tenant context.
        \App\Core\Session::forget('user_id');
        \App\Core\Session::forget('active_stokvel_id');
        \App\Core\Session::forget('stokvel_id');
        try {
            App::getInstance()->container()->get(\App\Core\TenantContext::class)->setUser(null);
            App::getInstance()->container()->get(\App\Core\TenantContext::class)->setTenant(null);
        } catch (\Throwable) {
        }
        return $result;
    } catch (\Throwable $e) {
        if ($db->pdo()->inTransaction()) {
            $db->rollBack();
        }
        \App\Core\Session::forget('user_id');
        \App\Core\Session::forget('active_stokvel_id');
        \App\Core\Session::forget('stokvel_id');
        try {
            App::getInstance()->container()->get(\App\Core\TenantContext::class)->setUser(null);
            App::getInstance()->container()->get(\App\Core\TenantContext::class)->setTenant(null);
        } catch (\Throwable) {
        }
        throw $e;
    }
}

function test_assert(bool $condition, string $message = 'Assertion failed'): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function test_block(string $message): never
{
    throw new Tests\BlockedTestException($message);
}
