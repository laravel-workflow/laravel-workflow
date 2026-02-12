<?php

declare(strict_types=1);

namespace Tests\Feature;

use RuntimeException;
use Tests\Fixtures\TestStressParentWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

class RaceConditionTest extends TestCase
{
    public function testParentWorkflowWithParallelChildWorkflows(int $children = 100, int $actPerChild = 10): void
    {
        $runId = (int) now()
            ->format('Uu');

        $workflow = WorkflowStub::make(TestStressParentWorkflow::class);
        $workflow->start($runId, $children, $actPerChild);

        $deadline = now()
            ->addSeconds(120);

        while ($workflow->running() && now()->lt($deadline)) {
            usleep(50000);
            $workflow->fresh();
        }

        if ($workflow->running()) {
            throw new RuntimeException(sprintf(
                'Race run %d did not complete before timeout. Current status: %s',
                $runId,
                (string) $workflow->status()
            ));
        }

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame([
            'run_id' => $runId,
            'children' => $children,
            'activities_per_child' => $actPerChild,
        ], $workflow->output());
    }
}
