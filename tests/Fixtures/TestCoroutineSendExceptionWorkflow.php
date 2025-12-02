<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use Workflow\ActivityStub;
use Workflow\Workflow;

class TestCoroutineSendExceptionWorkflow extends Workflow
{
    public function execute()
    {
        $result = yield ActivityStub::make(TestActivity::class);

        throw new Exception('exception test');
    }
}
