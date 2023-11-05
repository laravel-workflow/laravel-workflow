<?php

declare(strict_types=1);

namespace Workflow\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowStarted
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public int|string $workflowId,
        public string $class,
        public string $arguments,
        public string $timestamp,
    ) {
    }
}
