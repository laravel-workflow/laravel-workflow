<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestEmptyWorkflow extends Workflow
{
    public function execute()
    {
        return yield WorkflowStub::await(static fn () => true);
    }
}
