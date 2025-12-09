<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use function Workflow\activity;
use Workflow\Workflow;

final class TestFailingWorkflow extends Workflow
{
    public function execute($shouldFail = false)
    {
        $otherResult = yield activity(TestOtherActivity::class, 'other');

        if ($shouldFail) {
            throw new Exception('failed');
        }

        $result = yield activity(TestActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
