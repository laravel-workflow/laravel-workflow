<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;

final class TestExceptionWorkflow extends Workflow
{
    public function execute()
    {
        $otherResult = yield ActivityStub::make(TestOtherActivity::class, 'other');

        $result = yield ActivityStub::make(TestExceptionActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
