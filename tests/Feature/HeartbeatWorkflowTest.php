<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestHeartbeatWorkflow;
use Tests\TestCaseRequiringWorkers;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class HeartbeatWorkflowTest extends TestCaseRequiringWorkers
{
    public function testHeartbeat(): void
    {
        $workflow = WorkflowStub::make(TestHeartbeatWorkflow::class);

        $workflow->start();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
    }
}
