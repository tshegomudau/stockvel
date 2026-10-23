<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $attributes = [];

    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $server,
        private readonly array $cookies,
        private readonly array $files,
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $body = $_POST;
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $override = $_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;
            if (is_string($override) && $override !== '') {
                $method = strtoupper($override);
            }
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = self::stripApplicationBase($path);

        return new self($method, $path, $_GET, $body, $_SERVER, $_COOKIE, $_FILES);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? $default;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');
        if ($header && preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
            return $m[1];
        }
        return null;
    }

    public function rawBody(): string
    {
        return file_get_contents('php://input') ?: '';
    }

    /**
     * Strip subdirectory / public front-controller prefixes so routes match "/login"
     * for installs like http://localhost/projects/stovel/public/login.
     */
    private static function stripApplicationBase(string $path): string
    {
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/') ?: '/';
        }

        // Prefer PATH_INFO when the front controller is invoked as index.php/login.
        $pathInfo = $_SERVER['PATH_INFO'] ?? null;
        if (is_string($pathInfo) && $pathInfo !== '' && $pathInfo !== '/') {
            $path = '/' . ltrim(str_replace('\\', '/', $pathInfo), '/');
        }

        $candidates = [];
        if (function_exists('base_path_uri')) {
            $base = base_path_uri();
            if ($base !== '') {
                $candidates[] = $base;
            }
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && $scriptDir !== '.') {
            $candidates[] = $scriptDir;
            // Also try parent of /public (some installs rewrite away "public").
            if (str_ends_with($scriptDir, '/public')) {
                $candidates[] = substr($scriptDir, 0, -7) ?: '';
            }
        }

        $candidates = array_values(array_unique(array_filter($candidates, static fn ($c) => $c !== '')));
        usort($candidates, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($candidates as $base) {
            if ($path === $base) {
                $path = '/';
                break;
            }
            if (str_starts_with($path, $base . '/')) {
                $path = substr($path, strlen($base)) ?: '/';
                break;
            }
        }

        // Direct hits on the front controller file.
        if ($path === '/index.php' || $path === '/router.php') {
            $path = '/';
        }
        if (str_starts_with($path, '/index.php/')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
        }

        return $path === '' ? '/' : $path;
    }
}
