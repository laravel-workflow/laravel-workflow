<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

final class TestParentWorkflowSignalingChildDirectly extends Workflow
{
    public function execute()
    {
        $childPromise = ChildWorkflowStub::make(TestSimpleChildWorkflowWithSignal::class, 'direct_signaling');

        $childHandle = $this->child();

        $childHandle->approve('approved');

        $result = yield $childPromise;

        return $result;
    }
}
