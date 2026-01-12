<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use function Workflow\{activity, async, child};

class TestParentAsyncWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute()
    {
        $results = yield async(static function () {
            $otherResult = yield child(TestChildWorkflow::class);

            $result = yield activity(TestActivity::class);

            return [$otherResult, $result];
        });

        $otherResult = $results[0];
        $result = $results[1];

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
