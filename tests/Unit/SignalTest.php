<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Middleware\WithoutOverlappingMiddleware;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;
use Workflow\Signal;
use Workflow\States\WorkflowRunningStatus;
use Workflow\WorkflowStub;

final class SignalTest extends TestCase
{
    public function testMiddleware(): void
    {
        $signal = new Signal(new StoredWorkflow());

        $middleware = collect($signal->middleware())
            ->map(static fn ($middleware) => is_object($middleware) ? get_class($middleware) : $middleware)
            ->values();

        $this->assertCount(1, $middleware);
        $this->assertSame([WithoutOverlappingMiddleware::class], $middleware->all());
    }

    public function testSignalWorkflowRunning(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowRunningStatus::$name,
        ]);

        $signal = new Signal($storedWorkflow);
        $signal->handle();

        $this->assertSame(WorkflowRunningStatus::class, $workflow->status());
    }
}
