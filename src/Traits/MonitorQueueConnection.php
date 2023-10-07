<?php

declare(strict_types=1);

namespace Workflow\Traits;

trait MonitorQueueConnection
{
    public function viaConnection(): string
    {
        return config('workflows.monitor_connection', config('queue.default'));
    }

    public function viaQueue(): string
    {
        return config(
            'workflows.monitor_queue',
            config('queue.connections.' . $this->viaConnection() . '.queue', 'default')
        );
    }
}
