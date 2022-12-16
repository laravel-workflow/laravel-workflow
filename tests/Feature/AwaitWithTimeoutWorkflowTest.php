<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestAwaitWithTimeoutWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class AwaitWithTimeoutWorkflowTest extends TestCase
{
    public function testCompleted(): void
    {
        $workflow = WorkflowStub::make(TestAwaitWithTimeoutWorkflow::class);

        $now = now();

        $workflow->start(shouldTimeout: false);

        while ($workflow->running());

        $this->assertLessThan(5, now()->diffInSeconds($now));
        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow', $workflow->output());
    }

    public function testTimedout(): void
    {
        $workflow = WorkflowStub::make(TestAwaitWithTimeoutWorkflow::class);

        $now = now();

        $workflow->start(shouldTimeout: true);

        while ($workflow->running());

        $this->assertGreaterThanOrEqual(5, now()->diffInSeconds($now));
        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_timed_out', $workflow->output());
    }
}
