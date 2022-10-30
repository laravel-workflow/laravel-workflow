<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Fixtures\TestHeartbeatWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

class HeartbeatWorkflowTest extends TestCase
{
    public function testHeartbeat()
    {
        $workflow = WorkflowStub::make(TestHeartbeatWorkflow::class);

        $workflow->start();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
    }
}
