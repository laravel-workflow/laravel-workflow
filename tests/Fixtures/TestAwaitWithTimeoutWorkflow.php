<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use Workflow\WorkflowStub;

final class TestAwaitWithTimeoutWorkflow extends Workflow
{
    public function execute($shouldTimeout = false)
    {
        $result = yield WorkflowStub::awaitWithTimeout(5, static fn (): bool => ! $shouldTimeout);

        return $result ? 'workflow' : 'workflow_timed_out';
    }
}
