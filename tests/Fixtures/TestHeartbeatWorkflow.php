<?php

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;

class TestHeartbeatWorkflow extends Workflow
{
    public function execute()
    {
        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        $result = yield ActivityStub::make(TestHeartbeatActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
