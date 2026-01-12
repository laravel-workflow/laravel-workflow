<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\child;
use Workflow\Workflow;

class TestParentContinueAsNewChildWorkflow extends Workflow
{
    public function execute()
    {
        $childResult = yield child(TestChildContinueAsNewWorkflow::class);

        return 'parent_' . $childResult;
    }
}
