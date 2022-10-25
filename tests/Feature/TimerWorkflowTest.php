<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Fixtures\TestTimerWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

class TimerWorkflowTest extends TestCase
{
    public function testTimerWorkflow()
    {
        $workflow = WorkflowStub::make(TestTimerWorkflow::class);

        $now = now();

        $workflow->start(0);

        while ($workflow->running());

        $this->assertLessThan(5, now()->diffInSeconds($now));
        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow', $workflow->output());
    }

    public function testTimerWorkflowDelay()
    {
        $workflow = WorkflowStub::make(TestTimerWorkflow::class);

        $now = now();

        $workflow->start(5);

        while ($workflow->running());

        $this->assertGreaterThan(5, now()->diffInSeconds($now));
        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow', $workflow->output());
    }
}
