<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $secure = filter_var(env('SESSION_SECURE', false), FILTER_VALIDATE_BOOL);
        $sameSite = (string) env('SESSION_SAME_SITE', 'Lax');
        $lifetime = (int) env('SESSION_LIFETIME', 120) * 60;
        $savePath = self::resolveSavePath();
        $cookiePath = function_exists('base_path_uri') ? (base_path_uri() ?: '/') : '/';

        session_name((string) env('SESSION_NAME', 'stockvel_session'));
        session_save_path($savePath);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $cookiePath,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');

        // Suppress warnings if the save path is still not writable; fall back once.
        $started = @session_start();
        if (!$started || session_status() !== PHP_SESSION_ACTIVE) {
            $fallback = self::ensureDir(sys_get_temp_dir() . '/stovel_sessions_' . substr(hash('sha256', dirname(__DIR__, 2)), 0, 12));
            session_save_path($fallback);
            @session_start();
        }

        self::$started = session_status() === PHP_SESSION_ACTIVE;
    }

    private static function resolveSavePath(): string
    {
        $preferred = dirname(__DIR__, 2) . '/storage/sessions';
        $ensured = self::ensureDir($preferred);
        if (is_writable($ensured)) {
            return $ensured;
        }

        return self::ensureDir(sys_get_temp_dir() . '/stovel_sessions_' . substr(hash('sha256', dirname(__DIR__, 2)), 0, 12));
    }

    private static function ensureDir(string $path): string
    {
        if (!is_dir($path)) {
            @mkdir($path, 0777, true);
        }
        // Local/dev: allow PHP-FPM/Apache (often "nobody"/"www-data") to write sessions.
        if (is_dir($path) && !is_writable($path)) {
            @chmod($path, 0777);
        }

        return $path;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function regenerate(bool $deleteOld = true): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (headers_sent()) {
            return;
        }
        session_regenerate_id($deleteOld);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies') && !headers_sent() && PHP_SAPI !== 'cli') {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$started = false;
    }
}
