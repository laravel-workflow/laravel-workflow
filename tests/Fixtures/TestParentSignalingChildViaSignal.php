<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ChildWorkflowStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestParentSignalingChildViaSignal extends Workflow
{
    private bool $receivedSignal = false;

    private ?string $signalStatus = null;

    #[SignalMethod]
    public function forwardApproval(string $status): void
    {
        $this->receivedSignal = true;
        $this->signalStatus = $status;
    }

    public function execute()
    {
        $childPromise = ChildWorkflowStub::make(TestSimpleChildWorkflowWithSignal::class, 'forwarded');

        $childHandle = $this->child();

        yield WorkflowStub::await(fn () => $this->receivedSignal);

        if ($childHandle && $this->signalStatus) {
            $childHandle->approve($this->signalStatus);
        }

        $result = yield $childPromise;

        return $result;
    }
}
