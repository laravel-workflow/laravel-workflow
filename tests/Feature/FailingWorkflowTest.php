<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Fixtures\TestFailingWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\WorkflowStub;

class FailingWorkflowTest extends TestCase
{
    public function testRetry()
    {
        $workflow = WorkflowStub::make(TestFailingWorkflow::class);

        $workflow->start(shouldFail: true);

        while ($workflow->running());

        $this->assertSame(WorkflowFailedStatus::class, $workflow->status());
        $this->assertStringContainsString('failed', $workflow->output());

        $workflow->fresh()->start(shouldFail: false);

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
    }
}
