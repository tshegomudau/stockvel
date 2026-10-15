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

function url(string $path = ''): string
{
    $base = rtrim((string) env('APP_URL', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base === '' ? $path : $base . $path;
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function view(string $template, array $data = [], ?string $layout = 'layouts/app'): string
{
    return View::render($template, $data, $layout);
}

function money(float|string|null $amount, string $currency = 'ZAR'): string
{
    $amount = (float) ($amount ?? 0);
    return $currency . ' ' . number_format($amount, 2, '.', ',');
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
