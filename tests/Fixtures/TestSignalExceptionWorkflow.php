<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Throwable;
use Workflow\ActivityStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestSignalExceptionWorkflow extends Workflow
{
    protected bool $shouldRetry = false;

    #[SignalMethod]
    public function shouldRetry(): void
    {
        $this->shouldRetry = true;
    }

    public function execute(array $data = [])
    {
        $shouldThrow = true;
        while (true) {
            try {
                yield ActivityStub::make(TestActivity::class);
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
