<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\activity;
use Workflow\Workflow;

class TestSimpleWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute()
    {
        $result = yield activity(TestActivity::class);

        return 'workflow_' . $result;
    }
}
