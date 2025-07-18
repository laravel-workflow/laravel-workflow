<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestChildContinueAsNewWorkflow extends Workflow
{
    public function execute(int $count = 0, int $totalCount = 2)
    {
        if ($count >= $totalCount) {
            return 'child_done';
        }
        return yield WorkflowStub::continueAsNew($count + 1, $totalCount);
    }
}
