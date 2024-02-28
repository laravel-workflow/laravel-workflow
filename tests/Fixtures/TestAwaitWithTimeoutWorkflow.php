<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Generator;
use Workflow\Workflow;
use Workflow\WorkflowStub;

final class TestAwaitWithTimeoutWorkflow extends Workflow
{
    public function execute(bool $shouldTimeout = false): Generator
    {
        $result = yield WorkflowStub::awaitWithTimeout(5, static fn (): bool => ! $shouldTimeout);

        return $result ? 'workflow' : 'workflow_timed_out';
    }
}
