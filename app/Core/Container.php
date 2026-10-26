<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
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
                $resolved = $this->autowire($id);
                // Cache concrete class resolutions so nested graphs stay consistent per request.
                $this->instances[$id] = $resolved;

                return $resolved;
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

    /**
     * Build a class by resolving typed constructor dependencies from the container.
     * Interfaces and untyped required parameters must still be explicitly bound.
     */
    private function autowire(string $class): object
    {
        $ref = new ReflectionClass($class);
        if (!$ref->isInstantiable()) {
            throw new RuntimeException("Service not instantiable: {$class}");
        }

        $constructor = $ref->getConstructor();
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            $args[] = $this->resolveParameter($parameter, $class);
        }

        return $ref->newInstanceArgs($args);
    }

    private function resolveParameter(ReflectionParameter $parameter, string $owner): mixed
    {
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $dependency = $type->getName();
            try {
                return $this->get($dependency);
            } catch (RuntimeException $e) {
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }
                if ($parameter->allowsNull()) {
                    return null;
                }
                throw new RuntimeException(
                    "Unable to resolve {$dependency} for {$owner}::{$parameter->getName()}: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        if ($parameter->allowsNull()) {
            return null;
        }

        throw new RuntimeException(
            "Unable to autowire {$owner}::{$parameter->getName()} — bind it explicitly in bootstrap/register.php"
        );
    }
}
