<?php

declare(strict_types=1);

namespace Workflow;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\App;
use ReflectionFunction;

final class AsyncWorkflow extends Workflow
{
    /**
     * The container property is needed in the @see RouteDependencyResolverTrait
     * which in turn is used to dynamically resolve the "execute" method parameters.
     * @phpstan-ignore-next-line
     */
    private Container $container;

    public function execute($callback)
    {
        $this->container = App::make(Container::class);
        $callable = $callback->getClosure();
        $coroutine = $callable(...$this->resolveMethodDependencies([], new ReflectionFunction($callable)));
        return ($coroutine instanceof Generator) ? yield from $coroutine : $coroutine;
    }
}
