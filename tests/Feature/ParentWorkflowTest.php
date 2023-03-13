<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestActivity;
use Tests\Fixtures\TestChildWorkflow;
use Tests\Fixtures\TestParentWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class ParentWorkflowTest extends TestCase
{
    public function testCompleted(): void
    {
        $workflow = WorkflowStub::make(TestParentWorkflow::class);

        $workflow->start();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
        $this->assertSame([TestActivity::class, TestChildWorkflow::class], $workflow->logs()
            ->pluck('class')
            ->sort()
            ->values()
            ->toArray());
    }
}
