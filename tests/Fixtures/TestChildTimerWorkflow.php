<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestChildTimerWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute($seconds = 1)
    {
        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        yield WorkflowStub::timer($seconds);

        return $otherResult;
    }
}
