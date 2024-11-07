<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;

final class NonRetryableTestExceptionWorkflow extends Workflow
{
    public function execute()
    {
        yield ActivityStub::make(NonRetryableTestExceptionActivity::class);

        return "Workflow completes";
    }
}
