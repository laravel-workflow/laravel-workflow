<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestBatchContinueAsNewWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class ContinueAsNewBatchWorkflowTest extends TestCase
{
    public function testBatchActivitiesWithContinueAsNew(): void
    {
        $workflow = WorkflowStub::make(TestBatchContinueAsNewWorkflow::class);

        $workflow->start(20);

        while ($workflow->running());

        $this->assertEquals(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertIsArray($workflow->output());
        $this->assertCount(20, $workflow->output());
    }
}
