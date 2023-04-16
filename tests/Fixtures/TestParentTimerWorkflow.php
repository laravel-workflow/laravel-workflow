<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

class TestParentTimerWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute($seconds = 1)
    {
        $otherResult = yield ChildWorkflowStub::make(TestChildTimerWorkflow::class, $seconds);

        $result = yield ActivityStub::make(TestActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
