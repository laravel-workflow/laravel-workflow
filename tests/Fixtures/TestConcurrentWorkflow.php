<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;

final class TestConcurrentWorkflow extends Workflow
{
    public function execute()
    {
        $otherResultPromise = ActivityStub::make(TestOtherActivity::class, 'other');

        $resultPromise = ActivityStub::make(TestActivity::class);

        $results = yield ActivityStub::all([$otherResultPromise, $resultPromise]);

        return 'workflow_' . $results[1] . '_' . $results[0];
    }
}
