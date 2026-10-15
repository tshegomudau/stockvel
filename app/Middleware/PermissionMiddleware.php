<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Exceptions\HttpException;
use App\Services\PermissionService;

final class PermissionMiddleware implements MiddlewareInterface
{
    /** @var array<string, string> method + path => permission code */
    private static array $routePermissions = [];

    public function __construct(
        private readonly PermissionService $permissions,
        private readonly string $permissionCode = ''
    ) {
    }

    public static function route(string $method, string $path, string $permissionCode): void
    {
        $method = strtoupper($method);
        $path = rtrim($path, '/') ?: '/';
        self::$routePermissions["{$method} {$path}"] = $permissionCode;
    }

    public static function for(string $permissionCode): PermissionMiddlewareHandler
    {
        return new PermissionMiddlewareHandler($permissionCode);
    }

    public function handle(Request $request, callable $next): mixed
    {
        $code = $this->permissionCode;
        if ($code === '') {
            $code = (string) ($request->getAttribute('required_permission') ?? '');
        }
        if ($code === '') {
            $code = $this->resolveFromRoute($request) ?? '';
        }
        if ($code === '') {
            throw new HttpException(500, 'Permission middleware is not configured for this route.');
        }

        $this->permissions->require($code);

        return $next($request);
    }

    private function resolveFromRoute(Request $request): ?string
    {
        $path = rtrim($request->path(), '/') ?: '/';
        $key = $request->method() . ' ' . $path;
        return self::$routePermissions[$key] ?? null;
    }
}

final class PermissionMiddlewareHandler implements MiddlewareInterface
{
    public function __construct(
        private readonly string $permissionCode
    ) {
    }

    public function handle(Request $request, callable $next): mixed
    {
        $request->setAttribute('required_permission', $this->permissionCode);
        $middleware = app()->container()->get(PermissionMiddleware::class);
        return $middleware->handle($request, $next);
    }
}
