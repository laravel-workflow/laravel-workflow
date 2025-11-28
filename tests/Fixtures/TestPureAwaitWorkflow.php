<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

/**
 * Case 1: Pure await - no activities, just signal
 */
final class TestPureAwaitWorkflow extends Workflow
{
    public bool $approved = false;

    #[SignalMethod]
    public function approve(bool $value): void
    {
        $this->approved = $value;
    }

    public function execute()
    {
        yield WorkflowStub::await(fn () => $this->approved);

        return 'approved';
    }
}
