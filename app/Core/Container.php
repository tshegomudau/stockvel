<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

final class Container
{
    /** @var array<string, mixed> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function set(string $id, mixed $concrete): void
    {
        $this->bindings[$id] = $concrete;
    }

    public function singleton(string $id, Closure $factory): void
    {
        $this->bindings[$id] = function () use ($id, $factory) {
            if (!isset($this->instances[$id])) {
                $this->instances[$id] = $factory($this);
            }
            return $this->instances[$id];
        };
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->bindings[$id])) {
            if (class_exists($id)) {
                return new $id();
            }
            throw new RuntimeException("Service not found: {$id}");
        }

        $concrete = $this->bindings[$id];
        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        return $concrete;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || class_exists($id);
    }
}
