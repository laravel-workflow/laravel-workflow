<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Generator;
use Workflow\ActivityStub;
use Workflow\Workflow;

class TestChildWorkflow extends Workflow
{
    public function execute(): Generator
    {
        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        return $otherResult;
    }
}
