<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestAsyncWorkflow;
use Tests\TestCase;
use Workflow\AsyncWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class AsyncWorkflowTest extends TestCase
{
    public function testAsyncWorkflow(): void
    {
        echo "[TEST] Starting testAsyncWorkflow\n";

        echo "[TEST] Creating workflow stub\n";
        $workflow = WorkflowStub::make(TestAsyncWorkflow::class);
        echo "[TEST] Workflow stub created\n";

        echo "[TEST] Starting workflow\n";
        $workflow->start();
        echo "[TEST] Workflow started\n";

        echo "[TEST] Waiting for workflow to complete\n";
        $iterations = 0;
        while ($workflow->running()) {
            $iterations++;
            if ($iterations % 10 === 0) {
                echo "[TEST] Still waiting... (iteration {$iterations})\n";
            }
            usleep(100000); // 0.1 second
        }
        echo "[TEST] Workflow completed after {$iterations} iterations\n";

        echo "[TEST] Running assertions\n";
        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
        $this->assertSame([AsyncWorkflow::class], $workflow->logs()->pluck('class')->sort()->values()->toArray());
        echo "[TEST] Test completed successfully\n";
    }
}
