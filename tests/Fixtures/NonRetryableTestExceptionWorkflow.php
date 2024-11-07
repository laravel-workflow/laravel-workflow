<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use Workflow\ActivityStub;
use Tests\Fixtures\TestActivity;
use Tests\Fixtures\NonRetryableTestExceptionActivity;

final class NonRetryableTestExceptionWorkflow extends Workflow
{
    public function execute()
    {
        yield ActivityStub::make(NonRetryableTestExceptionActivity::class);
        yield ActivityStub::make(TestActivity::class);

        return 'Workflow completes';
    }
}
