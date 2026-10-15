<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use Closure;

final class Router
{
    /** @var array<int, array{methods: string[], path: string, handler: mixed, middleware: array}> */
    private array $routes = [];

    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add(['GET'], $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add(['POST'], $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add(['PUT', 'PATCH'], $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add(['PATCH', 'PUT'], $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add(['DELETE'], $path, $handler, $middleware);
    }

    public function add(array $methods, string $path, mixed $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'methods' => array_map('strtoupper', $methods),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request, Container $container): mixed
    {
        foreach ($this->routes as $route) {
            if (!in_array($request->method(), $route['methods'], true)) {
                continue;
            }
            $params = $this->match($route['path'], $request->path());
            if ($params === null) {
                continue;
            }

            foreach ($params as $key => $value) {
                $request->setAttribute($key, $value);
            }

            $handler = $this->resolveHandler($route['handler'], $container);
            $pipeline = array_reduce(
                array_reverse($route['middleware']),
                static function (callable $next, string $middlewareClass) use ($container) {
                    return static function (Request $req) use ($next, $middlewareClass, $container) {
                        $middleware = $container->get($middlewareClass);
                        return $middleware->handle($req, $next);
                    };
                },
                static function (Request $req) use ($handler, $params) {
                    return $handler($req, ...array_values($params));
                }
            );

            return $pipeline($request);
        }

        throw new HttpException(404, 'Page not found.');
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        $routePath = rtrim($routePath, '/') ?: '/';
        $requestPath = rtrim($requestPath, '/') ?: '/';
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';
        if (!preg_match($pattern, $requestPath, $matches)) {
            return null;
        }
        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }

    private function resolveHandler(mixed $handler, Container $container): callable
    {
        if ($handler instanceof Closure) {
            return $handler;
        }
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = $container->has($class) ? $container->get($class) : new $class();
            return static function (Request $request, ...$params) use ($controller, $method) {
                return $controller->{$method}($request, ...$params);
            };
        }
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $controller = $container->has($class) ? $container->get($class) : new $class();
            return static function (Request $request, ...$params) use ($controller, $method) {
                return $controller->{$method}($request, ...$params);
            };
        }
        throw new HttpException(500, 'Invalid route handler.');
    }
}
