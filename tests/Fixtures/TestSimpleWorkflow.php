<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Generator;
use Workflow\ActivityStub;
use Workflow\Workflow;

class TestSimpleWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute(): Generator
    {
        $result = yield ActivityStub::make(TestActivity::class);

        return 'workflow_' . $result;
    }
}
