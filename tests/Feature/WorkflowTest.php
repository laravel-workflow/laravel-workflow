<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class WorkflowTest extends TestCase
{
    public function testCompleted(): void
    {
        $workflow = WorkflowStub::make(TestWorkflow::class);

        $workflow->start(shouldAssert: false);

        $workflow->cancel();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
    }

    public function testCompletedDelay(): void
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
