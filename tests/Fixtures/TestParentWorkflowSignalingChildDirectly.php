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

        $childStub = $this->child();

        $childStub->approve('approved');

        $result = yield $childPromise;

        return $result;
    }
}
