<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Generator;
use Workflow\ActivityStub;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

class TestParentTimerWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute(int $seconds = 1): Generator
    {
        $otherResult = yield ChildWorkflowStub::make(TestChildTimerWorkflow::class, $seconds);

        $result = yield ActivityStub::make(TestActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
