<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in development server.
 * Serves existing public files (CSS/JS/images) directly; everything else goes to the front controller.
 *
 * Usage: php -S 127.0.0.1:8090 -t public public/router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
$path = rawurldecode($path);

if ($path !== '/' && $path !== '') {
    $file = __DIR__ . $path;
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/index.php';
