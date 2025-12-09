<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use function Workflow\{activity, child};

class TestParentTimerWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute($seconds = 1)
    {
        $otherResult = yield child(TestChildTimerWorkflow::class, $seconds);

        $result = yield activity(TestActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
