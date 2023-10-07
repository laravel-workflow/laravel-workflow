<?php

declare(strict_types=1);

namespace Workflow\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Workflow\Events\WorkflowFailed;
use Workflow\Traits\FetchesMonitorAuth;
use Workflow\Traits\MonitorQueueConnection;

class MonitorWorkflowFailed implements ShouldQueue
{
    use FetchesMonitorAuth;
    use MonitorQueueConnection;

    public function handle(WorkflowFailed $event): void
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
                'status' => 'failed',
                'updated_at' => $event->timestamp,
            ]);
    }
}
