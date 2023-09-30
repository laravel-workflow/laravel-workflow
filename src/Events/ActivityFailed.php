<?php

declare(strict_types=1);

namespace Workflow\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;

class ActivityFailed
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        public string $activityId,
        public string $output,
        public string $timestamp,
    ) {
    }
}
