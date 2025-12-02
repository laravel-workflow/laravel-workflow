<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\QueryMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

final class TestTimerQueryWorkflow extends Workflow
{
    private string $status = 'waiting';

    #[QueryMethod]
    public function getStatus(): string
    {
        return $this->status;
    }

    public function execute($seconds = 10)
    {
        yield WorkflowStub::timer($seconds);

        $this->status = 'completed';

        return 'done';
    }
}
