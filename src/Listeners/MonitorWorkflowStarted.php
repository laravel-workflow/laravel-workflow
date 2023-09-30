<?php

declare(strict_types=1);

namespace Workflow\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Workflow\Events\WorkflowStarted;
use Workflow\Traits\FetchesMonitorAuth;

class MonitorWorkflowStarted implements ShouldQueue
{
    use FetchesMonitorAuth;

    public function handle(WorkflowStarted $event): void
    {
        $auth = $this->auth();

        Http::withToken($auth['token'])
            ->withHeaders([
                'apiKey' => $auth['public'],
            ])
            ->post(config('workflows.monitor_url') . '/rest/v1/workflows', [
                'user_id' => $auth['user'],
                'workflow_id' => $event->workflowId,
                'class' => $event->class,
                'arguments' => $event->arguments,
                'status' => 'running',
                'created_at' => $event->timestamp,
            ]);
    }
}
