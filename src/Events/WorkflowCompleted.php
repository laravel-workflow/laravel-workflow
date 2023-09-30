<?php

declare(strict_types=1);

namespace Workflow\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowCompleted
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public int $workflowId,
        public string $output,
        public string $timestamp,
    ) {
    }
}
