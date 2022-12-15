<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestAwaitWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class AwaitWorkflowTest extends TestCase
{
    public function testCompleted(): void
    {
        $workflow = WorkflowStub::make(TestAwaitWorkflow::class);

        $workflow->start();

        $workflow->cancel();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow', $workflow->output());
    }

    public function testCompletedWithDelay(): void
    {
        $workflow = WorkflowStub::make(TestAwaitWorkflow::class);

        $workflow->start();

        sleep(5);

        $workflow->cancel();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow', $workflow->output());
    }
}
