<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;

final class CsrfMiddleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->input('_token')
                ?? $request->header('X-CSRF-TOKEN')
                ?? $request->header('X-XSRF-TOKEN');
            Csrf::validate(is_string($token) ? $token : null);
        }
        return $next($request);
    }
}
