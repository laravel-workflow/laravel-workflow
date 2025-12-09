<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use function Workflow\{activity, child};

class TestParentExceptionWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute($shouldThrow = false)
    {
        $otherResult = yield child(TestChildExceptionWorkflow::class, $shouldThrow);

        $result = yield activity(TestActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
