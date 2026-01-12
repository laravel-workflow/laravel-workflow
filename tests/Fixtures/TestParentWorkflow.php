<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\SignalMethod;
use Workflow\Workflow;
use function Workflow\{activity, child};

class TestParentWorkflow extends Workflow
{
    #[SignalMethod]
    public function ping(): void
    {
        // Do nothing
    }

    public function execute()
    {
        $otherResult = yield child(TestChildWorkflow::class);

        $result = yield activity(TestActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
