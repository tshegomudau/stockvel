<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $relative = substr($class, strlen('App\\'));
        $path = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
        return;
    }
    if (str_starts_with($class, 'Tests\\')) {
        $relative = substr($class, strlen('Tests\\'));
        $path = dirname(__DIR__) . '/tests/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});

require_once dirname(__DIR__) . '/app/Helpers/functions.php';
