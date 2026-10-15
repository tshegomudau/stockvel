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
            Csrf::validate($request->input('_token'));
        }
        return $next($request);
    }
}
