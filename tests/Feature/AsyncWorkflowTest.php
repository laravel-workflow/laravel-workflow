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
    public function __construct()
    {
        file_put_contents('php://stderr', "[TEST-CONSTRUCT] AsyncWorkflowTest::__construct() called\n");
        parent::__construct(...func_get_args());
        file_put_contents('php://stderr', "[TEST-CONSTRUCT] AsyncWorkflowTest::__construct() finished\n");
    }

    protected function setUp(): void
    {
        file_put_contents('php://stderr', "[TEST-SETUP] AsyncWorkflowTest::setUp() ENTERED\n");
        parent::setUp();
        file_put_contents('php://stderr', "[TEST-SETUP] AsyncWorkflowTest::setUp() FINISHED\n");
    }

    public function testAsyncWorkflow(): void
    {
        file_put_contents('php://stderr', "\n\n[TEST] ==================== TEST METHOD STARTED ====================\n");
        echo "\n\n[TEST] ==================== TEST METHOD STARTED ====================\n";
        flush();

        echo "[TEST] Creating workflow stub\n";
        flush();

        $workflow = WorkflowStub::make(TestAsyncWorkflow::class);

        echo "[TEST] Workflow stub created\n";
        flush();

        echo "[TEST] Starting workflow\n";
        flush();

        $workflow->start();

        echo "[TEST] Workflow started\n";
        flush();

        echo "[TEST] Waiting for workflow to complete\n";
        flush();

        $iterations = 0;
        while ($workflow->running()) {
            $iterations++;
            if ($iterations % 10 === 0) {
                echo "[TEST] Still waiting... (iteration {$iterations})\n";
                flush();
            }
            usleep(100000); // 0.1 second
        }

        echo "[TEST] Workflow completed after {$iterations} iterations\n";
        flush();

        echo "[TEST] Running assertions\n";
        flush();

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
        $this->assertSame([AsyncWorkflow::class], $workflow->logs()->pluck('class')->sort()->values()->toArray());
        echo "[TEST] Test completed successfully\n";
    }
}
