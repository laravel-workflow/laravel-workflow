<?php

declare(strict_types=1);

namespace Workflow\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Workflow\Events\WorkflowCompleted;
use Workflow\Traits\FetchesMonitorAuth;

class MonitorWorkflowCompleted implements ShouldQueue
{
    use FetchesMonitorAuth;

    public function handle(WorkflowCompleted $event): void
    {
        $auth = $this->auth();

        Http::withToken($auth['token'])
            ->withHeaders([
                'apiKey' => $auth['public'],
            ])
            ->withOptions([
                'query' => [
                    'user_id' => 'eq.' . $auth['user'],
                    'workflow_id' => 'eq.' . $event->workflowId,
                ],
            ])
            ->patch(config('workflows.monitor_url') . '/rest/v1/workflows', [
                'output' => $event->output,
                'status' => 'completed',
                'updated_at' => $event->timestamp,
            ]);
    }
}
