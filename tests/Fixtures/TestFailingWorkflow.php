<?php

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;

class TestFailingWorkflow extends Workflow
{
    public function execute($shouldFail = false)
    {
        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        if ($shouldFail) {
            $result = yield ActivityStub::make(TestFailingActivity::class);
        } else {
            $result = yield ActivityStub::make(TestActivity::class);
        }

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
