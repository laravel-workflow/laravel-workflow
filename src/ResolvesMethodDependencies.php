<?php

declare(strict_types=1);

namespace Workflow;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Reflector;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use stdClass;

/**
 * @source https://github.com/laravel/framework/blob/10.x/src/Illuminate/Routing/ResolvesRouteDependencies.php
 */
class ResolvesMethodDependencies
{
    public function __construct(
        private Container $container
    ) {
    }

    public function handle(array $parameters, object $instance, string $method): array
    {
        if (! method_exists($instance, $method)) {
            throw new \InvalidArgumentException('Method not found');
        }

        return $this->resolveMethodDependencies(
            $parameters,
            new ReflectionMethod($instance, $method)
        );
    }

    public function resolveMethodDependencies(array $parameters, ReflectionMethod $reflector): array
    {
        $instanceCount = 0;

        $values = array_values($parameters);

        $skippableValue = new stdClass();

        foreach ($reflector->getParameters() as $key => $parameter) {
            $instance = $this->transformDependency($parameter, $parameters, $skippableValue);

            if ($instance !== $skippableValue) {
                ++$instanceCount;

                $this->spliceIntoParameters($parameters, $key, $instance);
            } elseif (! isset($values[$key - $instanceCount])
                && $parameter->isDefaultValueAvailable()) {
                $this->spliceIntoParameters($parameters, $key, $parameter->getDefaultValue());
            }
        }

        return $parameters;
    }

    protected function transformDependency(
        ReflectionParameter $parameter,
        $parameters,
        stdClass $skippableValue
    ): mixed {
        $className = Reflector::getParameterClassName($parameter);

        if ($className && ! $this->alreadyInParameters($className, $parameters)) {
            $isEnum = (new ReflectionClass($className))->isEnum();

            return $parameter->isDefaultValueAvailable()
                ? ($isEnum ? $parameter->getDefaultValue() : null)
                : $this->container->make($className);
        }

        return $skippableValue;
    }

    protected function alreadyInParameters(?string $class, array $parameters): bool
    {
        return Arr::first($parameters, static fn ($value) => $value instanceof $class) !== null;
    }

    protected function spliceIntoParameters(array &$parameters, int $offset, mixed $value): void
    {
        array_splice($parameters, $offset, 0, [$value]);
    }
}
