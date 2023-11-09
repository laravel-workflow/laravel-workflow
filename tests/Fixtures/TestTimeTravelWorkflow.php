<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\QueryMethod;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestTimeTravelWorkflow extends Workflow
{
    private bool $canceled = false;

    #[SignalMethod]
    public function cancel(): void
    {
        $this->canceled = true;
    }

    #[QueryMethod]
    public function isCanceled(): bool
    {
        return $this->canceled;
    }

    public function execute()
    {
        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        yield WorkflowStub::await(fn (): bool => $this->canceled);

        $result = yield ActivityStub::make(TestActivity::class);

        yield WorkflowStub::timer(60);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
