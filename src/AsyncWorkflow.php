<?php

declare(strict_types=1);

namespace Workflow;

use Generator;

final class AsyncWorkflow extends Workflow
{
    public function execute($callback)
    {
        $coroutine = ($callback->getClosure())();
        return ($coroutine instanceof Generator) ? yield from $coroutine : $coroutine;
    }
}
