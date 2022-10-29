<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Fixtures\TestExceptionWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

class ExceptionWorkflowTest extends TestCase
{
    public function testRetry()
    {
        $workflow = WorkflowStub::make(TestExceptionWorkflow::class);

        $workflow->start();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
    }
}
