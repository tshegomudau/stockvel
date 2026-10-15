<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Services\Auth\AuthService;

final class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthService $auth
    ) {
    }

    public function handle(Request $request, callable $next): mixed
    {
        if ($this->auth->check()) {
            redirect(url('/dashboard'));
        }

        return $next($request);
    }
}
