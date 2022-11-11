<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use Workflow\ActivityStub;
use Workflow\Workflow;

final class TestFailingWorkflow extends Workflow
{
    public function execute($shouldFail = false)
    {
        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        if ($shouldFail) {
            throw new Exception('failed');
        }

        $result = yield ActivityStub::make(TestActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
