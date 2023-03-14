<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;

class TestChildWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute()
    {
        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        return $otherResult;
    }
}
