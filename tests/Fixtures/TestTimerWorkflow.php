<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Generator;
use Workflow\Workflow;
use Workflow\WorkflowStub;

final class TestTimerWorkflow extends Workflow
{
    public function execute(int $seconds = 1): Generator
    {
        yield WorkflowStub::timer($seconds);

        return 'workflow';
    }
}
