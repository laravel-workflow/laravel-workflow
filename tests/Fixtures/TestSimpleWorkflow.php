<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\Foundation\Application;
use Workflow\ActivityStub;
use Workflow\QueryMethod;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestSimpleWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute()
    {
        $result = yield ActivityStub::make(TestActivity::class);

        return 'workflow_' . $result;
    }
}
