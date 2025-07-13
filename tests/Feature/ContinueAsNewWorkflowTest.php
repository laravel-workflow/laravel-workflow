<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestContinueAsNewWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class ContinueAsNewWorkflowTest extends TestCase
{
    public function testCompleted(): void
    {
        $workflow = WorkflowStub::make(TestContinueAsNewWorkflow::class);

        $workflow->start();

        while ($workflow->running());

        $this->assertEquals(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertEquals('workflow_3', $workflow->output());

        $workflow = WorkflowStub::load(2);

        $this->assertEquals(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertEquals('workflow_3', $workflow->output());
    }
}
