<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
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
            // Expired/anonymous session: land on the public home page (no "sign in required" error).
            return Response::redirect(url('/'));
        }
        return $next($request);
    }
}
