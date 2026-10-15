<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Services\Auth\AuthService;

final class AuthMiddleware
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function handle(Request $request, callable $next): mixed
    {
        $this->auth->hydrateTenantFromSession();
        if (!Session::get('user_id')) {
            throw new HttpException(401, 'Authentication required.');
        }
        return $next($request);
    }
}
