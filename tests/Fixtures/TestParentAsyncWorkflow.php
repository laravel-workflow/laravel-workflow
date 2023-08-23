<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

class TestParentAsyncWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute()
    {
        $results = yield ActivityStub::async(static function () {
            $otherResult = yield ChildWorkflowStub::make(TestChildWorkflow::class);

            $result = yield ActivityStub::make(TestActivity::class);

            return [$otherResult, $result];
        });

        $otherResult = $results[0];
        $result = $results[1];

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
