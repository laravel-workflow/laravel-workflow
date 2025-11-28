<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestSingleTryExceptionActivity;
use Throwable;
use Workflow\ActivityStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

/**
 * Case 6: Activity throws -> await -> retry pattern
 */
final class TestActivityThrowsAwaitRetryWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    protected bool $shouldRetry = false;

    #[SignalMethod]
    public function shouldRetry(): void
    {
        $this->shouldRetry = true;
    }

    public function execute()
    {
        $shouldThrow = true;
        while (true) {
            try {
                yield ActivityStub::make(TestSingleTryExceptionActivity::class, $shouldThrow);
                return true;
            } catch (Throwable) {
                yield WorkflowStub::await(fn () => $this->shouldRetry);
                $this->shouldRetry = false;
                $shouldThrow = false;
            }
        }
    }
}
