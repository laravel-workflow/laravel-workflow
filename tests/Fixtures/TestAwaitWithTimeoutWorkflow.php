<?php

namespace Tests\Fixtures;

use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestAwaitWithTimeoutWorkflow extends Workflow
{
    public function execute($shouldTimeout = false)
    {
        $result = yield WorkflowStub::awaitWithTimeout(5, fn () => !$shouldTimeout);

        return $result ? 'workflow' : 'workflow_timed_out';
    }
}
