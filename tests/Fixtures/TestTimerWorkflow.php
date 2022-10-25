<?php

namespace Tests\Fixtures;

use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestTimerWorkflow extends Workflow
{
    public function execute($seconds = 1)
    {
        yield WorkflowStub::timer($seconds);

        return 'workflow';
    }
}
