<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Generator;
use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestChildTimerWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute(int $seconds = 1): Generator
    {
        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        yield WorkflowStub::timer($seconds);

        return $otherResult;
    }
}
