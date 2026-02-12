<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use RuntimeException;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;
use Tests\Fixtures\StressParentWorkflow;

class RaceConditionTest extends TestCase
{
    public function testParentWorkflowWithParallelChildWorkflows(int $children = 100, int $actPerChild = 10): void
    {
        $runId = (int) now()
            ->format('Uu');

        $workflow = WorkflowStub::make(StressParentWorkflow::class);
        $workflow->start($runId, $children, $actPerChild);

        $deadline = now()
            ->addSeconds(30);

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
