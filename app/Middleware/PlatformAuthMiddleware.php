<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Exceptions\AuthorizationException;
use App\Services\Auth\AuthService;

final class PlatformAuthMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private const ALLOWED_ROLES = [
        'platform_super_admin',
        'platform_support',
        'platform_auditor',
        'super_admin',
        'support',
        'auditor',
    ];

    public function __construct(
        private readonly AuthService $auth
    ) {
    }

    public function handle(Request $request, callable $next): mixed
    {
        if (!$this->auth->check()) {
            throw new AuthorizationException('Platform authentication required.');
        }

        $user = $this->auth->user();
        $role = $user['platform_role'] ?? null;
        if (!is_string($role) || !in_array($role, self::ALLOWED_ROLES, true)) {
            throw new AuthorizationException('Platform access denied.');
        }

        $request->setAttribute('platform_role', $role);

        return $next($request);
    }
}
