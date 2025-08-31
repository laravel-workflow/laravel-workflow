<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestContinueAsNewWorkflow extends Workflow
{
    public function execute(int $count = 0, int $totalCount = 3)
    {
        $activityResult = yield ActivityStub::make(TestCountActivity::class, $count);

        if ($count >= $totalCount) {
            return 'workflow_' . $activityResult;
        }

        return yield WorkflowStub::continueAsNew($count + 1, $totalCount);
    }
}
