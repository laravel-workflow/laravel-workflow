<?php

declare(strict_types=1);

namespace Workflow\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Workflow\Events\ActivityFailed;
use Workflow\Traits\FetchesMonitorAuth;
use Workflow\Traits\MonitorQueueConnection;

class MonitorActivityFailed implements ShouldQueue
{
    use FetchesMonitorAuth;
    use MonitorQueueConnection;

    public function handle(ActivityFailed $event): void
    {
        $auth = $this->auth();

        Http::withToken($auth['token'])
            ->withHeaders([
                'apiKey' => $auth['public'],
            ])
            ->withOptions([
                'query' => [
                    'id' => 'eq.' . $event->activityId,
                ],
            ])
            ->patch(config('workflows.monitor_url') . '/rest/v1/activities', [
                'output' => $event->output,
                'status' => 'failed',
                'updated_at' => $event->timestamp,
            ]);
    }
}
