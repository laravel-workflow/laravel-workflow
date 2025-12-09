<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Throwable;
use Workflow\SignalMethod;
use Workflow\Workflow;
use function Workflow\{activity, await};

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
