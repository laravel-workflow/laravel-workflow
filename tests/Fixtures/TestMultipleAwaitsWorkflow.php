<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

/**
 * Case 3: Multiple sequential awaits
 */
final class TestMultipleAwaitsWorkflow extends Workflow
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
        yield WorkflowStub::await(fn () => $this->firstApproved);

        yield WorkflowStub::await(fn () => $this->secondApproved);

        return 'both_approved';
    }
}
