<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;

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
