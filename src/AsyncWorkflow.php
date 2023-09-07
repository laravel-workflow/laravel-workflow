<?php

declare(strict_types=1);

namespace Workflow;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\App;
use ReflectionFunction;

final class AsyncWorkflow extends Workflow
{
    private Container $container;

    public function execute($callback)
    {
        $this->container = App::make(Container::class);
        $callable = $callback->getClosure();
        $coroutine = $callable(...$this->resolveMethodDependencies([], new ReflectionFunction($callable)));
        return ($coroutine instanceof Generator) ? yield from $coroutine : $coroutine;
    }
}
