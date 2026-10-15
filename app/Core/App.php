<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class App
{
    private static ?self $instance = null;
    private array $config = [];

    public function __construct(
        private readonly Container $container,
        private readonly string $basePath
    ) {
        self::$instance = $this;
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            throw new \RuntimeException('Application not bootstrapped.');
        }
        return self::$instance;
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function run(): void
    {
        try {
            $request = Request::capture();
            $router = $this->container->get(Router::class);
            $result = $router->dispatch($request, $this->container);
            if ($result instanceof Response) {
                $result->send();
            }
            if (is_string($result)) {
                Response::html($result)->send();
            }
            if (is_array($result)) {
                Response::json($result)->send();
            }
        } catch (Throwable $e) {
            $this->container->get(ExceptionHandler::class)->handle($e);
        }
    }
}
