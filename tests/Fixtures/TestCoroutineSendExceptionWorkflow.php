<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use function Workflow\activity;
use Workflow\Workflow;

class TestCoroutineSendExceptionWorkflow extends Workflow
{
    public function execute()
    {
        $result = yield activity(TestActivity::class);

        throw new Exception('exception test');
    }
}
