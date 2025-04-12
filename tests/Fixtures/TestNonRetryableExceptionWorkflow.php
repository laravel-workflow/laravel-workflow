<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;

final class TestNonRetryableExceptionWorkflow extends Workflow
{
    public function execute()
    {
        yield ActivityStub::make(TestNonRetryableExceptionActivity::class);
        yield ActivityStub::make(TestActivity::class);

        return 'Workflow completes';
    }
}
