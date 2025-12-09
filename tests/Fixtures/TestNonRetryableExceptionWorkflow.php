<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\activity;
use Workflow\Workflow;

final class TestNonRetryableExceptionWorkflow extends Workflow
{
    public function execute()
    {
        yield activity(TestNonRetryableExceptionActivity::class);
        yield activity(TestActivity::class);

        return 'Workflow completes';
    }
}
