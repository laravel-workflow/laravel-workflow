<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use function Workflow\{activity, continueAsNew};

class TestContinueAsNewWorkflow extends Workflow
{
    public function execute(int $count = 0, int $totalCount = 3)
    {
        $activityResult = yield activity(TestCountActivity::class, $count);

        if ($count >= $totalCount) {
            return 'workflow_' . $activityResult;
        }

        return yield continueAsNew($count + 1, $totalCount);
    }
}
