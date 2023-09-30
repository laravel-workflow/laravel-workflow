<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;
use Workflow\Events\ActivityFailed;
use Workflow\Listeners\MonitorActivityFailed;

final class MonitorActivityFailedTest extends TestCase
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
            "rest/v1/activities?id=eq.{$activityId}" => Http::response(),
        ]);

        $event = new ActivityFailed($activityId, 'output', 'time');
        $listener = new MonitorActivityFailed();
        $listener->handle($event);

        Http::assertSent(static function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer key') &&
                $request->url() === 'http://test/functions/v1/get-user';
        });

        Http::assertSent(static function (Request $request) use ($activityId) {
            $data = json_decode($request->body());
            return $request->hasHeader('apiKey', 'public') &&
                $request->hasHeader('Authorization', 'Bearer token') &&
                $request->url() === "http://test/rest/v1/activities?id=eq.{$activityId}" &&
                $data->status === 'failed' &&
                $data->output === 'output' &&
                $data->updated_at === 'time';
        });
    }
}
