<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestContinuesWorkflow;
use Tests\Fixtures\TestOtherActivity;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class ContinuesWorkflowTest extends TestCase
{
    public function testCompleted(): void
    {
        $workflow = WorkflowStub::make(TestContinuesWorkflow::class);
        $id = $workflow->id();
        $workflow->start();

        while ($workflow->running());

        $this->assertSame($id, $workflow->id());
        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame(['9', '10'], $workflow->output());
        $this->assertSame([TestOtherActivity::class, TestOtherActivity::class], $workflow->logs()
            ->pluck('class')
            ->sort()
            ->values()
            ->toArray());
    }
}
