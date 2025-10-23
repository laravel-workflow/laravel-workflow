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

        file_put_contents('php://stderr', "[TEST] About to call WorkflowStub::make\n");
        echo "[TEST] About to call WorkflowStub::make\n";
        flush();

        $workflow = WorkflowStub::make(TestAsyncWorkflow::class);

        file_put_contents('php://stderr', "[TEST] WorkflowStub::make returned\n");
        echo "[TEST] WorkflowStub::make returned\n";
        flush();

        file_put_contents('php://stderr', "[TEST] About to call workflow->start()\n");
        echo "[TEST] About to call workflow->start()\n";
        flush();

        $workflow->start();

        file_put_contents('php://stderr', "[TEST] workflow->start() returned\n");
        echo "[TEST] workflow->start() returned\n";
        flush();

        file_put_contents('php://stderr', "[TEST] About to enter while loop, checking workflow->running()\n");
        echo "[TEST] About to enter while loop, checking workflow->running()\n";
        flush();

        $iterations = 0;
        $maxIterations = 100; // 10 seconds max
        while ($workflow->running() && $iterations < $maxIterations) {
            $iterations++;
            if ($iterations === 1) {
                file_put_contents('php://stderr', "[TEST] First iteration of while loop\n");
                echo "[TEST] First iteration of while loop\n";
                flush();
            }
            if ($iterations % 10 === 0) {
                file_put_contents('php://stderr', "[TEST] Still waiting... (iteration {$iterations})\n");
                echo "[TEST] Still waiting... (iteration {$iterations})\n";
                flush();
            }
            usleep(100000); // 0.1 second
        }

        if ($iterations >= $maxIterations) {
            file_put_contents('php://stderr', "[TEST] TIMEOUT! Printing Laravel log:\n");
            echo "[TEST] TIMEOUT! Checking for errors...\n";
            $logPath = __DIR__ . '/../../vendor/orchestra/testbench-core/laravel/storage/logs/laravel.log';
            if (file_exists($logPath)) {
                $log = file_get_contents($logPath);
                file_put_contents('php://stderr', "===== LARAVEL LOG =====\n" . $log . "\n=====\n");
                echo "===== LARAVEL LOG =====\n" . $log . "\n=====\n";
            } else {
                file_put_contents('php://stderr', "[TEST] No laravel.log found at {$logPath}\n");
            }
            $this->fail('Workflow did not complete within timeout');
        }

        file_put_contents('php://stderr', "[TEST] Exited while loop\n");

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
