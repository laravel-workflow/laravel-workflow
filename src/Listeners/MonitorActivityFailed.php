<?php

declare(strict_types=1);

namespace Workflow\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Workflow\Events\ActivityFailed;

class MonitorActivityFailed implements ShouldQueue
{
    public function handle(ActivityFailed $event): void
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
            ->withQueryParameters([
                'id' => 'eq.' . $event->activityId,
            ])
            ->patch(config('workflows.monitor_url') . '/rest/v1/activities', [
                'output' => $event->output,
                'status' => 'failed',
                'updated_at' => $event->timestamp,
            ]);
    }
}
