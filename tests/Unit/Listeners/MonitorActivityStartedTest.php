<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use Workflow\Events\ActivityStarted;
use Workflow\Listeners\MonitorActivityStarted;

final class MonitorActivityStartedTest extends TestCase
{
    public function testHandle(): void
    {
        config([
            'workflows.monitor_url' => 'http://test',
        ]);
        config([
            'workflows.monitor_api_key' => 'key',
        ]);

        $activityId = (string) Str::uuid();

        Http::fake([
            'functions/v1/get-user' => Http::response([
                'user' => 'user',
                'public' => 'public',
                'token' => 'token',
            ]),
            'rest/v1/activities' => Http::response(),
        ]);

        $event = new ActivityStarted(1, $activityId, 'class', 0, 'arguments', 'time');
        $listener = new MonitorActivityStarted();
        $listener->handle($event);

        Http::assertSent(static function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer key') &&
                $request->url() === 'http://test/functions/v1/get-user';
        });

        Http::assertSent(static function (Request $request) use ($activityId) {
            $data = json_decode($request->body());
            return $request->hasHeader('apiKey', 'public') &&
                $request->hasHeader('Authorization', 'Bearer token') &&
                $request->url() === 'http://test/rest/v1/activities' &&
                $data->user_id === 'user' &&
                $data->workflow_id === 1 &&
                $data->id === $activityId &&
                $data->index === 0 &&
                $data->class === 'class' &&
                $data->status === 'running' &&
                $data->arguments === 'arguments' &&
                $data->created_at === 'time';
        });
    }
}
