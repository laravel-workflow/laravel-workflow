<?php

declare(strict_types=1);

namespace Workflow\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Workflow\Events\ActivityCompleted;
use Workflow\Traits\FetchesMonitorAuth;
use Workflow\Traits\MonitorQueueConnection;

class MonitorActivityCompleted implements ShouldQueue
{
    use FetchesMonitorAuth;
    use MonitorQueueConnection;

    public function handle(ActivityCompleted $event): void
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
                'status' => 'completed',
                'updated_at' => $event->timestamp,
            ]);
    }
}
