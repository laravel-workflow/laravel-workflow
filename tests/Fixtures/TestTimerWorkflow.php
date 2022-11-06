<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use Workflow\WorkflowStub;

final class TestTimerWorkflow extends Workflow
{
    public function execute($seconds = 1)
    {
        yield WorkflowStub::timer($seconds);

        return 'workflow';
    }
}
