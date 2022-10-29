<?php

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestWorkflow extends Workflow
{
    private bool $canceled = false;

    #[SignalMethod]
    public function cancel()
    {
        $this->canceled = true;
    }

    public function execute($shouldAssert = false)
    {
        if ($shouldAssert)
            assert($this->canceled === false);

        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        if ($shouldAssert)
            assert($this->canceled === false);

        $result = yield ActivityStub::make(TestActivity::class);

        yield WorkflowStub::await(fn () => $this->canceled);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
