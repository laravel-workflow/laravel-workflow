<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\SignalMethod;
use Workflow\Workflow;
use function Workflow\{await, child};

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
        $childPromise = child(TestSimpleChildWorkflowWithSignal::class, 'forwarded');

        $childHandle = $this->child();

        yield await(fn () => $this->receivedSignal);

        if ($childHandle && $this->signalStatus) {
            $childHandle->approve($this->signalStatus);
        }

        $result = yield $childPromise;

        return $result;
    }
}
