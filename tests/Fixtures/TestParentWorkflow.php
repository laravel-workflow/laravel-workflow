<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

class TestParentWorkflow extends Workflow
{
    public function execute()
    {
        $otherResult = yield ChildWorkflowStub::make(TestChildWorkflow::class);

        $result = yield ActivityStub::make(TestActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
