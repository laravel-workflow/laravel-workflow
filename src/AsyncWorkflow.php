<?php

declare(strict_types=1);

namespace Workflow;

use Generator;
use ReflectionFunction;

final class AsyncWorkflow extends Workflow
{
    public function execute($callback)
    {
        $callable = $callback->getClosure();
        $coroutine = $callable(...$this->resolveMethodDependencies([], new ReflectionFunction($callable)));
        return ($coroutine instanceof Generator) ? yield from $coroutine : $coroutine;
    }
}
