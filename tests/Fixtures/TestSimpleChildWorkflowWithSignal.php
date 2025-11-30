<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestSimpleChildWorkflowWithSignal extends Workflow
{
    private ?string $approved = null;

    #[SignalMethod]
    public function approve(string $status): void
    {
        $this->approved = $status;
    }

    public function execute(string $prefix)
    {
        yield WorkflowStub::await(fn () => $this->approved !== null);

        return $prefix . '_' . $this->approved;
    }
}
