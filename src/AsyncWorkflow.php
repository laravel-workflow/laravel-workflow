<?php

declare(strict_types=1);

namespace Workflow;

final class AsyncWorkflow extends Workflow
{
    public function execute($callback)
    {
        return yield from ($callback->getClosure())();
    }
}
