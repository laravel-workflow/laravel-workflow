<?php

declare(strict_types=1);

namespace Workflow\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Workflow\Events\WorkflowStarted;

class MonitorWorkflowStarted implements ShouldQueue
{
    public function handle(WorkflowStarted $event): void
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
