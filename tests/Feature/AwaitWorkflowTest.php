<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Fixtures\TestAwaitWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

class AwaitWorkflowTest extends TestCase
{
    public function testCompleted()
    {
        $workflow = WorkflowStub::make(TestAwaitWorkflow::class);

        $workflow->start();

        $workflow->cancel();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow', $workflow->output());
    }

    public function testCompletedWithDelay()
    {
        $workflow = WorkflowStub::make(TestAwaitWorkflow::class);

        $workflow->start();

        sleep(5);

        $workflow->cancel();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow', $workflow->output());
    }
}
