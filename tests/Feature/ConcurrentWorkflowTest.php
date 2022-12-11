<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestActivity;
use Tests\Fixtures\TestOtherActivity;
use Tests\Fixtures\TestConcurrentWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class ConcurrentWorkflowTest extends TestCase
{
    public function testCompleted(): void
    {
        $this->markTestSkipped('Skip concurrent workflow test.');
        return;

        $workflow = WorkflowStub::make(TestConcurrentWorkflow::class);

        $workflow->start();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
        $this->assertSame([TestOtherActivity::class, TestActivity::class], $workflow->logs()
            ->pluck('class')
            ->toArray());
    }
}
