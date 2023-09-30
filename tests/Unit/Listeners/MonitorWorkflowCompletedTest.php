<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Workflow\Events\WorkflowCompleted;
use Workflow\Listeners\MonitorWorkflowCompleted;

final class MonitorWorkflowCompletedTest extends TestCase
{
    public function testHandle(): void
    {
        config([
            'workflows.monitor_url' => 'http://test',
        ]);
        config([
            'workflows.monitor_api_key' => 'key',
        ]);

        Http::fake([
            'functions/v1/get-user' => Http::response([
                'user' => 'user',
                'public' => 'public',
                'token' => 'token',
            ]),
            'rest/v1/workflows?user_id=eq.user&workflow_id=eq.1' => Http::response(),
        ]);

        $event = new WorkflowCompleted(1, 'output', 'time');
        $listener = new MonitorWorkflowCompleted();
        $listener->handle($event);

        Http::assertSent(static function (Request $request) {
            return $request->hasHeader('Authorization', 'Bearer key') &&
                $request->url() === 'http://test/functions/v1/get-user';
        });

        Http::assertSent(static function (Request $request) {
            $data = json_decode($request->body());
            return $request->hasHeader('apiKey', 'public') &&
                $request->hasHeader('Authorization', 'Bearer token') &&
                $request->url() === 'http://test/rest/v1/workflows?user_id=eq.user&workflow_id=eq.1' &&
                $data->status === 'completed' &&
                $data->output === 'output' &&
                $data->updated_at === 'time';
        });
    }
}
