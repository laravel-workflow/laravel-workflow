<?php

namespace Tests;

use Workflow\ActivityStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestWorkflow extends Workflow
{
    private bool $canceled;

    #[SignalMethod]
    public function cancel()
    {
        $this->canceled = true;
    }

    public function execute($shouldFail = false, $shouldAssert = false)
    {
        $this->canceled = false;

        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        if ($shouldAssert) {
            assert($this->canceled === false);
        }

        if ($shouldFail) {
            $result = yield ActivityStub::make(TestFailingActivity::class);
        } else {
            $result = yield ActivityStub::make(TestActivity::class);
        }

        yield WorkflowStub::await(fn () => $this->canceled);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
