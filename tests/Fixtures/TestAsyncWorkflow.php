<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;

final class TestAsyncWorkflow extends Workflow
{
    public function execute()
    {
        $results = yield ActivityStub::async(static function () {
            $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

            $result = yield ActivityStub::make(TestActivity::class);

            return [$otherResult, $result];
        });

        $otherResult = $results[0];
        $result = $results[1];

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
