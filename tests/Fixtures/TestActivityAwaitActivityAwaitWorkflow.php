<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

final class TestActivityAwaitActivityAwaitWorkflow extends Workflow
{
    public bool $firstApproved = false;

    public bool $secondApproved = false;

    #[SignalMethod]
    public function approveFirst(bool $value): void
    {
        $this->firstApproved = $value;
    }

    #[SignalMethod]
    public function approveSecond(bool $value): void
    {
        $this->secondApproved = $value;
    }

    public function execute()
    {
        yield ActivityStub::make(TestActivity::class);

        yield WorkflowStub::await(fn () => $this->firstApproved);

        yield ActivityStub::make(TestActivity::class);

        yield WorkflowStub::await(fn () => $this->secondApproved);

        return 'completed';
    }
}
