<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use Exception;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $abstract, $concrete = null, bool $singleton = false): void
    {
        if (is_null($concrete)) {
            $concrete = $abstract;
        }
        $this->bindings[$abstract] = compact('concrete', 'singleton');
    }

    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function make(string $abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $isSingleton = isset($this->bindings[$abstract]) && $this->bindings[$abstract]['singleton'];

        if (!isset($this->bindings[$abstract])) {
            $object = $this->resolve($abstract);
        } else {
            $concrete = $this->bindings[$abstract]['concrete'];

            if ($concrete instanceof \Closure) {
                $object = $concrete($this);
            } elseif (is_string($concrete) && $abstract !== $concrete) {
                $object = $this->make($concrete);
            } else {
                $object = $this->resolve($concrete);
            }
        }

        if ($isSingleton) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    private function resolve(string $class): object
    {
        try {
            $reflector = new ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new Exception("Class {$class} does not exist.");
        }

        if (!$reflector->isInstantiable()) {
            throw new Exception("Class {$class} is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $class;
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new Exception("Cannot resolve untyped or built-in parameter \${$parameter->name} in class {$class}");
            }
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    public function get(string $id)
    {
        return $this->make($id);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || class_exists($id);
    }
}
