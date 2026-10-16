<?php

declare(strict_types=1);

namespace App\Core;

final class Env
{
    public static function load(string $path, bool $override = true): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            if (!$override && (array_key_exists($key, $_ENV) || getenv($key) !== false)) {
                continue;
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function resolveNameFromArgv(array $argv): ?string
    {
        foreach ($argv as $arg) {
            if (!is_string($arg)) {
                continue;
            }
            if (str_starts_with($arg, '--env=')) {
                return substr($arg, 6);
            }
            if ($arg === '--env') {
                return null;
            }
        }

        return null;
    }
}
