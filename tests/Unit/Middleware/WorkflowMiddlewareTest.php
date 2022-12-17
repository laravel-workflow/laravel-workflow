<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\Fixtures\TestActivity;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Middleware\WorkflowMiddleware;
use Workflow\Models\StoredWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowRunningStatus;
use Workflow\States\WorkflowWaitingStatus;
use Workflow\WorkflowStub;

final class WorkflowMiddlewareTest extends TestCase
{
    public function testMiddleware(): void
    {
        Queue::fake();

        $workflow = WorkflowStub::make(TestWorkflow::class);
        $workflow->start();

        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'status' => WorkflowWaitingStatus::class,
        ]);

        $activity = $this->mock(TestActivity::class);
        $activity->index = 0;
        $activity->now = now()
            ->toDateTimeString();
        $activity->storedWorkflow = $storedWorkflow;

        $middleware = new WorkflowMiddleware();

        $middleware->handle($activity, static function ($job) {
            return true;
        });

        Queue::assertPushed(TestWorkflow::class, 2);
    }

    public function testAlreadyCompleted(): void
    {
        Queue::fake();

        $workflow = WorkflowStub::make(TestWorkflow::class);
        $workflow->start();

        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'status' => WorkflowCompletedStatus::class,
        ]);

        $activity = $this->mock(TestActivity::class);
        $activity->index = 0;
        $activity->now = now()
            ->toDateTimeString();
        $activity->storedWorkflow = $storedWorkflow;

        $middleware = new WorkflowMiddleware();

        $middleware->handle($activity, static function ($job) {
            return true;
        });

        Queue::assertPushed(TestWorkflow::class, 1);
    }

    public function testAlreadyRunning(): void
    {
        Queue::fake();

        $workflow = WorkflowStub::make(TestWorkflow::class);
        $workflow->start();

        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'status' => WorkflowRunningStatus::class,
        ]);

        $activity = $this->mock(TestActivity::class, static function (MockInterface $mock) {
            $mock->shouldReceive('release')
                ->once();
        });
        $activity->index = 0;
        $activity->now = now()
            ->toDateTimeString();
        $activity->storedWorkflow = $storedWorkflow;

        $middleware = new WorkflowMiddleware();

        $middleware->handle($activity, static function ($job) {
            return true;
        });

        Queue::assertPushed(TestWorkflow::class, 1);
    }
}
