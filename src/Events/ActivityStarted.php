<?php

declare(strict_types=1);

namespace Workflow\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

class ActivityStarted
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public int|string $workflowId,
        public string $activityId,
        public string $class,
        public int $index,
        public string $arguments,
        public string $timestamp,
    ) {
    }
}
