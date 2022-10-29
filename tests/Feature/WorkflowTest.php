<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Fixtures\TestWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

class WorkflowTest extends TestCase
{
    public function testCompleted()
    {
        $workflow = WorkflowStub::make(TestWorkflow::class);

        $workflow->start(shouldAssert: false);

        $workflow->cancel();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
    }

    public function testCompletedDelay()
    {
        $workflow = WorkflowStub::make(TestWorkflow::class);

        $workflow->start(shouldAssert: true);

        sleep(5);

        $workflow->cancel();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
    }
}
