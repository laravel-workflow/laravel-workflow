<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\child;
use Workflow\Workflow;

final class TestParentWorkflowSignalingChildDirectly extends Workflow
{
    public function execute()
    {
        $childPromise = child(TestSimpleChildWorkflowWithSignal::class, 'direct_signaling');

        $childHandle = $this->child();

        $childHandle->approve('approved');

        $result = yield $childPromise;

        return $result;
    }
}
