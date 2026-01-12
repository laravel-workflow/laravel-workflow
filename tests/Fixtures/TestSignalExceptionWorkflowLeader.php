<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Throwable;
use Workflow\SignalMethod;
use Workflow\Workflow;
use function Workflow\{activity, await};

class TestSignalExceptionWorkflowLeader extends Workflow
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
                yield activity(TestSingleTryExceptionActivity::class, $shouldThrow);
                return true;
            } catch (Throwable) {
                yield await(fn () => $this->shouldRetry);
                $this->shouldRetry = false;
                $shouldThrow = false;
            }
        }
    }
}
