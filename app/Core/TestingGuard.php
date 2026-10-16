<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class TestingGuard
{
    public static function isTestDatabaseName(string $database): bool
    {
        $database = strtolower(trim($database));
        if ($database === '') {
            return false;
        }

        return (bool) preg_match('/(^|_)test(ing)?(_|$)/', $database)
            || str_ends_with($database, '_testing')
            || str_starts_with($database, 'test_');
    }

    public static function assertSafeForDestructiveOps(string $database, string $operation): void
    {
        if (!self::isTestDatabaseName($database)) {
            throw new RuntimeException(
                "Refusing {$operation}: database '{$database}' is not identified as a test database. "
                . 'Configure DB_DATABASE/TEST_DB_DATABASE to a name containing test/testing (e.g. stockvel_testing).'
            );
        }
    }

    public static function currentDatabaseName(): string
    {
        $useTest = env('APP_ENV') === 'testing'
            || (php_sapi_name() === 'cli' && in_array('test', $_SERVER['argv'] ?? [], true));

        if ($useTest && env('TEST_DB_DATABASE')) {
            return (string) env('TEST_DB_DATABASE');
        }

        return (string) env('DB_DATABASE', '');
    }
}
