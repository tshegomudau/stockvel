<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

function config(string $key, mixed $default = null): mixed
{
    return App::getInstance()->config($key, $default);
}

function app(): App
{
    return App::getInstance();
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    $old = Session::getFlash('_old', []);
    return $old[$key] ?? $default;
}

function flash(string $key, mixed $value = null): mixed
{
    if ($value !== null) {
        Session::flash($key, $value);
        return null;
    }
    return Session::getFlash($key);
}

function redirect(string $url, int $status = 302): never
{
    header('Location: ' . $url, true, $status);
    exit;
}

/**
 * Application base path (subdirectory), normalised, no trailing slash.
 * Empty string when installed at the web root.
 */
function base_path_uri(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $configured = trim((string) env('APP_BASE_PATH', ''));
    if ($configured !== '' && $configured !== '/') {
        $cached = '/' . trim($configured, '/');
        return $cached;
    }

    // Auto-detect from script location when APP_BASE_PATH is empty.
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if ($dir === '' || $dir === '/' || $dir === '.') {
        $cached = '';
        return $cached;
    }
    // When using public/index.php, dirname is often /public or a subdirectory.
    if (str_ends_with($dir, '/public')) {
        $dir = substr($dir, 0, -7) ?: '';
    }
    $cached = $dir === '/' ? '' : $dir;
    return $cached;
}

/**
 * Absolute application URL (scheme + host + optional base path + path).
 */
function url(string $path = ''): string
{
    $appUrl = rtrim((string) env('APP_URL', ''), '/');
    $basePath = base_path_uri();
    $path = '/' . ltrim($path, '/');
    if ($path === '/') {
        $path = '';
    }

    if ($appUrl !== '') {
        return $appUrl . $basePath . ($path === '' ? '/' : $path);
    }

    // Relative-to-host URL when APP_URL is empty (works for any host/port).
    return ($basePath === '' ? '' : $basePath) . ($path === '' ? '/' : $path);
}

/**
 * Public asset URL with cache busting.
 * Accepts "css/app.css" or "assets/css/app.css".
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    if (!str_starts_with($path, 'assets/')) {
        $path = 'assets/' . $path;
    }

    $version = (string) env('ASSET_VERSION', '');
    if ($version === '' || strtolower((string) env('APP_ENV', '')) === 'local') {
        $file = dirname(__DIR__, 2) . '/public/' . $path;
        if (is_file($file)) {
            $version = (string) filemtime($file);
        }
    }

    $url = url($path);
    if ($version !== '') {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . rawurlencode($version);
    }

    return $url;
}

/**
 * Named route helper (path templates). Falls back to url() for absolute paths.
 *
 * @param array<string, scalar> $parameters
 */
function route(string $name, array $parameters = []): string
{
    static $routes = [
        'home' => '/',
        'login' => '/login',
        'register' => '/register',
        'logout' => '/logout',
        'forgot' => '/forgot-password',
        'app.dashboard' => '/app',
        'app.members' => '/app/members',
        'app.contributions' => '/app/contributions',
        'app.fees' => '/app/fees',
        'app.expenses' => '/app/expenses',
        'app.loans' => '/app/loans',
        'app.payouts' => '/app/payouts',
        'app.meetings' => '/app/meetings',
        'app.rules' => '/app/rules',
        'app.documents' => '/app/documents',
        'app.reports' => '/app/reports',
        'app.approvals' => '/app/approvals',
        'app.settings' => '/app/settings',
        'app.notifications' => '/app/notifications',
        'app.onboarding' => '/app/onboarding',
        'member.dashboard' => '/member',
        'member.statement' => '/member/statement',
        'member.profile' => '/member/profile',
        'platform.dashboard' => '/platform',
        'platform.tenants' => '/platform/tenants',
        'platform.users' => '/platform/users',
        'platform.audit' => '/platform/audit',
        'platform.jobs' => '/platform/jobs',
    ];

    $path = $routes[$name] ?? (str_starts_with($name, '/') ? $name : '/' . ltrim($name, '/'));
    foreach ($parameters as $key => $value) {
        $path = str_replace('{' . $key . '}', rawurlencode((string) $value), $path);
    }

    return url($path);
}

function view(string $template, array $data = [], ?string $layout = 'layouts/app'): string
{
    return View::render($template, $data, $layout);
}

function money(float|string|null $amount, string $currency = 'ZAR'): string
{
    $amount = (float) ($amount ?? 0);
    $formatted = number_format($amount, 2, '.', ' ');
    $prefix = strtoupper($currency) === 'ZAR' ? 'R' : $currency;
    return $prefix . ' ' . $formatted;
}

function sa_date(?string $datetime, string $format = 'd M Y'): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }
    $tz = env('APP_TIMEZONE', 'Africa/Johannesburg');
    $dt = new DateTimeImmutable($datetime, new DateTimeZone('UTC'));
    return $dt->setTimezone(new DateTimeZone($tz))->format($format);
}

function sa_datetime(?string $datetime): string
{
    return sa_date($datetime, 'd M Y H:i');
}

function now_utc(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

function uuid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function mask_bank(?string $value, int $visible = 4): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $len = strlen($value);
    if ($len <= $visible) {
        return str_repeat('*', $len);
    }
    return str_repeat('*', max(0, $len - $visible)) . substr($value, -$visible);
}

function request_id(): string
{
    static $id = null;
    if ($id === null) {
        $id = $_SERVER['HTTP_X_REQUEST_ID'] ?? uuid();
    }
    return $id;
}

function initials(?string $name): string
{
    $name = trim((string) $name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/', $name) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $letters !== '' ? $letters : '?';
}
