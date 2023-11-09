<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;

class TestChildWorkflow extends Workflow
{
    public function execute()
    {
        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        return $otherResult;
    }
}
