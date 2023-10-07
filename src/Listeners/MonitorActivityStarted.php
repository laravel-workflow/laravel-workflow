<?php

declare(strict_types=1);

namespace Workflow\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Workflow\Events\ActivityStarted;
use Workflow\Traits\FetchesMonitorAuth;
use Workflow\Traits\MonitorQueueConnection;

class MonitorActivityStarted implements ShouldQueue
{
    use FetchesMonitorAuth;
    use MonitorQueueConnection;

    public function handle(ActivityStarted $event): void
    {
        $auth = $this->auth();

        Http::withToken($auth['token'])
            ->withHeaders([
                'apiKey' => $auth['public'],
            ])
            ->post(config('workflows.monitor_url') . '/rest/v1/activities', [
                'id' => $event->activityId,
                'user_id' => $auth['user'],
                'workflow_id' => $event->workflowId,
                'class' => $event->class,
                'index' => $event->index,
                'arguments' => $event->arguments,
                'status' => 'running',
                'created_at' => $event->timestamp,
            ]);
    }
}
