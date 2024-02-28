<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Generator;
use Workflow\ActivityStub;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

class TestParentExceptionWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute(bool $shouldThrow = false): Generator
    {
        $otherResult = yield ChildWorkflowStub::make(TestChildExceptionWorkflow::class, $shouldThrow);

        $result = yield ActivityStub::make(TestActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
