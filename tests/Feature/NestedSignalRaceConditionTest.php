<?php

declare(strict_types=1);

namespace Tests\Feature;

use RuntimeException;
use Tests\Fixtures\TestNestedSignalLeafWorkflow;
use Tests\Fixtures\TestNestedSignalParentWorkflow;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class NestedSignalRaceConditionTest extends TestCase
{
    public function testNestedChildWorkflowsWithDuplicateSignalsDoNotGetStuckPending(): void
    {
        $runId = (int) now()
            ->format('Uu');
        $middleCount = 12;
        $leafCount = 3;
        $duplicateSignals = 4;
        $expectedLeafCount = $middleCount * $leafCount;

        $workflow = WorkflowStub::make(TestNestedSignalParentWorkflow::class);
        $workflow->start($runId, $middleCount, $leafCount);

        $creationDeadline = now()
            ->addSeconds(30);
        $leafIds = [];
        while (now()->lt($creationDeadline)) {
            $leafIds = StoredWorkflow::query()
                ->where('class', TestNestedSignalLeafWorkflow::class)
                ->pluck('id')
                ->all();

            if (count($leafIds) === $expectedLeafCount) {
                break;
            }

            usleep(50000);
        }

        $this->assertCount($expectedLeafCount, $leafIds, 'Timed out waiting for all nested leaf workflows');

        for ($round = 0; $round < $duplicateSignals; $round++) {
            foreach ($leafIds as $leafId) {
                WorkflowStub::load((int) $leafId)->respond();
            }
        }

        $completionDeadline = now()
            ->addSeconds(120);
        while ($workflow->running() && now()->lt($completionDeadline)) {
            usleep(50000);
            $workflow->fresh();
        }

        if ($workflow->running()) {
            throw new RuntimeException(sprintf(
                'Nested signal run %d did not complete before timeout. Current status: %s',
                $runId,
                (string) $workflow->status()
            ));
        }

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame([
            'run_id' => $runId,
            'middle_count' => $middleCount,
            'leaf_count' => $leafCount,
            'resolved_leaf_count' => $expectedLeafCount,
        ], $workflow->output());
    }
}
