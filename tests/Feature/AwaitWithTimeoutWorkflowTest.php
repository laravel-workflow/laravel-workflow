<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Fixtures\TestAwaitWithTimeoutWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

class AwaitWithTimeoutWorkflowTest extends TestCase
{
    public function testCompleted()
    {
        $workflow = WorkflowStub::make(TestAwaitWithTimeoutWorkflow::class);

        $now = now();

        $workflow->start(shouldTimeout: false);

        while ($workflow->running());

        $this->assertLessThan(5, now()->diffInSeconds($now));
        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow', $workflow->output());
    }

    public function testTimedout()
    {
        $workflow = WorkflowStub::make(TestAwaitWithTimeoutWorkflow::class);

        $now = now();

        $workflow->start(shouldTimeout: true);

        while ($workflow->running());

        $this->assertGreaterThan(5, now()->diffInSeconds($now));
        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_timed_out', $workflow->output());
    }
}
