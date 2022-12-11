<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestFailingWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\WorkflowStub;

final class FailingWorkflowTest extends TestCase
{
    public function testRetry(): void
    {
        $workflow = WorkflowStub::make(TestFailingWorkflow::class);

        $workflow->start(shouldFail: true);

        while ($workflow->running());

        $this->assertSame(WorkflowFailedStatus::class, $workflow->status());
        $this->assertNull($workflow->output());

        $workflow->fresh()
            ->start(shouldFail: false);

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
    }
}
