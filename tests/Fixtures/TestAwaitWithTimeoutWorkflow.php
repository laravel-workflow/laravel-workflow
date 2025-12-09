<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\awaitWithTimeout;
use Workflow\Workflow;

final class TestAwaitWithTimeoutWorkflow extends Workflow
{
    public function execute($shouldTimeout = false)
    {
        $result = yield awaitWithTimeout(5, static fn (): bool => ! $shouldTimeout);

        return $result ? 'workflow' : 'workflow_timed_out';
    }
}
