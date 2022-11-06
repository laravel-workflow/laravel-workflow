<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

final class TestAwaitWorkflow extends Workflow
{
    private bool $canceled = false;

    #[SignalMethod]
    public function cancel(): void
    {
        $this->canceled = true;
    }

    public function execute()
    {
        yield WorkflowStub::await(fn (): bool => $this->canceled);

        return 'workflow';
    }
}
