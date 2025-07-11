<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestContinueAsNewWorkflow extends Workflow
{
    public function execute($count = 0)
    {
        if ($count >= 3) {
            return 'workflow_' . $count;
        }

        return yield WorkflowStub::continueAsNew($count + 1);
    }
}
