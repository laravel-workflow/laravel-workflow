<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class MyWorkflow extends Workflow
{
    protected int $step = 0;
    protected bool $shouldRetry = false;

    #[SignalMethod]
    public function shouldRetry(): void
    {
        $this->shouldRetry = true;
    }

    public function execute(array $data = [])
    {
        $loops = 0;
        $result0 = yield ActivityStub::make(ActivityZero::class);

        while (true) {
            $loops++;
            try {
                if ($this->step < 1) {
                    $result1 = yield ActivityStub::make(ActivityOne::class, $loops > 1);
                    $this->step = 1;
                }

                if ($this->step < 2) {
                    $result2 = yield ActivityStub::make(ActivityTwo::class, $data);
                    $this->step = 2;
                }

                if ($this->step < 3) {
                    $result3 = yield ActivityStub::make(ActivityThree::class);
                    $this->step = 3;
                }

                if ($this->step < 4) {
                    $result4 = yield ActivityStub::make(ActivityFour::class, $data);
                    $this->step = 4;
                }

                return true;
            } catch (\Throwable $e) {
                yield WorkflowStub::await(fn () => $this->shouldRetry);
                $this->shouldRetry = false;
            }
        }
    }
}