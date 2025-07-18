<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

class TestParentContinueAsNewChildWorkflow extends Workflow
{
    public function execute()
    {
        $childResult = yield ChildWorkflowStub::make(TestChildContinueAsNewWorkflow::class);
        return 'parent_' . $childResult;
    }
}
