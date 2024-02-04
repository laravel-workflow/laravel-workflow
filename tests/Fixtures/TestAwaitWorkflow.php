<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Generator;
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

    public function execute(): Generator
    {
        yield WorkflowStub::await(fn (): bool => $this->canceled);

        return 'workflow';
    }
}
