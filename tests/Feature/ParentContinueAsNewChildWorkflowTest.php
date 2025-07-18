<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestParentContinueAsNewChildWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class ParentContinueAsNewChildWorkflowTest extends TestCase
{
    public function testChildWorkflowContinuesAsNew(): void
    {
        $workflow = WorkflowStub::make(TestParentContinueAsNewChildWorkflow::class);

        $workflow->start();

        while ($workflow->running());

        $this->assertEquals(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertEquals('parent_child_done', $workflow->output());
    }
}
