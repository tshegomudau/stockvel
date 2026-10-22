<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use Throwable;

final class ExceptionHandler
{
    /** @var array<int, string> */
    private const STATUS_MESSAGES = [
        401 => 'Please sign in to continue.',
        403 => 'You do not have permission to access this page.',
        404 => 'The page you requested could not be found.',
        419 => 'Your session expired. Please refresh the page and try again.',
        422 => 'The submitted information could not be processed. Please check and try again.',
        429 => 'Too many requests. Please wait a moment and try again.',
        500 => 'An unexpected error occurred. Please try again later.',
        503 => 'The service is temporarily unavailable. Please try again later.',
    ];

    /** @var array<int, string> */
    private const STATUS_TITLES = [
        401 => 'Sign in required',
        403 => 'Access denied',
        404 => 'Page not found',
        419 => 'Session expired',
        422 => 'Invalid request',
        429 => 'Too many requests',
        500 => 'Something went wrong',
        503 => 'Service unavailable',
    ];

    public function __construct(private readonly Logger $logger)
    {
    }

    public function handle(Throwable $e): never
    {
        $status = $e instanceof HttpException ? $e->getStatus() : 500;
        if ($status >= 500) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } else {
            $this->logger->warning($e->getMessage(), ['status' => $status]);
        }

        $env = strtolower((string) env('APP_ENV', 'production'));
        $isProduction = $env === 'production' || $env === 'prod';
        $debug = !$isProduction && filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL);

        $defaultMessage = self::STATUS_MESSAGES[$status] ?? self::STATUS_MESSAGES[500];
        $rawMessage = trim($e->getMessage());
        if ($status >= 500 && !$debug) {
            $message = $defaultMessage;
        } elseif ($rawMessage !== '' && $rawMessage !== 'HTTP Error') {
            $message = $rawMessage;
        } else {
            $message = $defaultMessage;
        }

        $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/');

        if ($wantsJson) {
            Response::json([
                'error' => $message,
                'status' => $status,
                'debug' => $debug ? [
                    'exception' => $e::class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], $status)->send();
        }

        $user = null;
        $platformRole = '';
        try {
            $uid = Session::get('user_id');
            if ($uid !== null) {
                $user = ['id' => (int) $uid];
                try {
                    $row = app()->container()->get(\App\Core\Database::class)->fetch(
                        'SELECT platform_role FROM users WHERE id = :id LIMIT 1',
                        ['id' => (int) $uid]
                    );
                    $platformRole = (string) ($row['platform_role'] ?? '');
                    if ($platformRole !== '') {
                        $user['platform_role'] = $platformRole;
                    }
                } catch (Throwable) {
                }
            }
        } catch (Throwable) {
            $user = null;
        }

        $dashboardUrl = $this->resolveDashboardUrl($user, $platformRole);
        $safeBackUrl = $this->resolveSafeBackUrl($dashboardUrl);

        try {
            $html = View::render('errors/error', [
                'status' => $status,
                'code' => $status,
                'message' => $message,
                'heading' => self::STATUS_TITLES[$status] ?? ('Error ' . $status),
                'user' => $user,
                'dashboardUrl' => $dashboardUrl,
                'safeBackUrl' => $safeBackUrl,
                'debug' => null,
                'title' => self::STATUS_TITLES[$status] ?? ('Error ' . $status),
            ], 'layouts/guest');
        } catch (Throwable) {
            $html = '<h1>' . e((string) $status) . '</h1><p>' . e($message) . '</p>'
                . '<p><a href="' . e($dashboardUrl) . '">Continue</a></p>';
        }
        Response::html($html, $status)->send();
    }

    /** @param array{id?: int, platform_role?: string}|null $user */
    private function resolveDashboardUrl(?array $user, string $platformRole): string
    {
        if ($user === null || empty($user['id'])) {
            return url('/login');
        }

        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $base = base_path_uri();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        $isPlatformAdmin = in_array($platformRole, [
            'platform_super_admin',
            'platform_support',
            'platform_auditor',
            'super_admin',
            'support',
            'auditor',
        ], true);

        // Platform operators always land on the platform dashboard — even when the
        // failing URL was under /app or /member (e.g. missing stokvel context).
        if ($isPlatformAdmin) {
            return url('/platform');
        }
        if (str_starts_with($path, '/member')) {
            return url('/member');
        }

        return url('/app');
    }

    private function resolveSafeBackUrl(string $dashboardUrl): string
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer === '') {
            return $dashboardUrl;
        }

        $appUrl = rtrim((string) env('APP_URL', ''), '/');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $origin = $appUrl !== '' ? $appUrl : ($host !== '' ? $scheme . '://' . $host : '');

        if ($origin !== '' && (str_starts_with($referer, $origin . '/') || $referer === $origin)) {
            return $referer;
        }

        // Relative same-host referer fallback when APP_URL is empty.
        if ($appUrl === '' && $host !== '') {
            $refHost = parse_url($referer, PHP_URL_HOST);
            if (is_string($refHost) && strcasecmp($refHost, $host) === 0) {
                return $referer;
            }
        }

        return $dashboardUrl;
    }
}
