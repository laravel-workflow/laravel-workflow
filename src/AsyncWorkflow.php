<?php

declare(strict_types=1);

namespace Workflow;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\App;
use Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;
use Laravel\SerializableClosure\SerializableClosure;
use ReflectionException;
use ReflectionFunction;

/**
 * @template TReturn
 */
final class AsyncWorkflow extends Workflow
{
    /**
     * The container property is needed in the @see RouteDependencyResolverTrait
     * which in turn is used to dynamically resolve the "execute" method parameters.
     * @phpstan-ignore-next-line
     */
    private Container $container;

    /**
     * @param SerializableClosure $callback
     * @return Generator<int, mixed, void, TReturn>
     * @throws ReflectionException
     * @throws PhpVersionNotSupportedException
     */
    public function execute($callback): Generator
    {
        $this->container = App::make(Container::class);
        $callable = $callback->getClosure();
        $coroutine = $callable(...$this->resolveMethodDependencies([], new ReflectionFunction($callable)));
        return ($coroutine instanceof Generator) ? yield from $coroutine : $coroutine;
    }
}
