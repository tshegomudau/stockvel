<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'stockvel'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => env('APP_URL', 'http://localhost'),
    'base_path' => env('APP_BASE_PATH', ''),
    'asset_version' => env('ASSET_VERSION', '1'),
    'key' => env('APP_KEY', ''),
    'timezone' => env('APP_TIMEZONE', 'Africa/Johannesburg'),
    'currency' => env('APP_CURRENCY', 'ZAR'),
    'locale' => env('APP_LOCALE', 'en_ZA'),
    'password_algo' => env('PASSWORD_ALGO', 'argon2id'),
    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
    'upload_max_mb' => (int) env('UPLOAD_MAX_MB', 10),
    'document_retention_days' => (int) env('DOCUMENT_RETENTION_DAYS', 2555),
    'cron_lock_ttl' => (int) env('CRON_LOCK_TTL', 300),
];
