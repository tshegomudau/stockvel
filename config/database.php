<?php

declare(strict_types=1);

$prefix = env('TEST_DB_PREFIX', '');
$useTest = env('APP_ENV') === 'testing' || (php_sapi_name() === 'cli' && in_array('test', $_SERVER['argv'] ?? [], true));

return [
    'host' => $useTest && env('TEST_DB_HOST') ? env('TEST_DB_HOST') : env('DB_HOST', '127.0.0.1'),
    'port' => $useTest && env('TEST_DB_PORT') ? env('TEST_DB_PORT') : env('DB_PORT', '3306'),
    'database' => $useTest && env('TEST_DB_DATABASE') ? env('TEST_DB_DATABASE') : env('DB_DATABASE', 'stockvel'),
    'username' => $useTest && env('TEST_DB_USERNAME') ? env('TEST_DB_USERNAME') : env('DB_USERNAME', 'root'),
    'password' => $useTest && env('TEST_DB_PASSWORD') !== null ? env('TEST_DB_PASSWORD') : env('DB_PASSWORD', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
    'prefix' => $prefix,
    'migrations_table' => ($prefix !== '' ? $prefix : '') . 'migrations',
];
