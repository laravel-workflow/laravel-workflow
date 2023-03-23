<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use Workflow\ActivityStub;
use Workflow\Workflow;

class TestChildExceptionWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute($shouldThrow = false)
    {
        if ($shouldThrow) {
            throw new Exception('failed');
        }

        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        return $otherResult;
    }
}
