<?php

declare(strict_types=1);

namespace Workflow\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Workflow\Events\WorkflowCompleted;

class MonitorWorkflowCompleted implements ShouldQueue
{
    public function handle(WorkflowCompleted $event): void
    {
        $auth = Cache::remember('users', 360, static function () {
            return Http::withToken(config('workflows.monitor_api_key'))
                ->get(config('workflows.monitor_url') . '/functions/v1/get-user')
                ->json();
        });

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
