<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): mixed
    {
        $response = $next($request);

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
        header('X-XSS-Protection: 0');

        $appUrl = rtrim((string) env('APP_URL', ''), '/');
        $csp = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
        ];
        if ($appUrl !== '') {
            $csp[] = "upgrade-insecure-requests";
        }
        header('Content-Security-Policy: ' . implode('; ', $csp));

        return $response;
    }
}
