<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\activity;
use Workflow\Workflow;

final class TestHeartbeatWorkflow extends Workflow
{
    public function execute()
    {
        $otherResult = yield activity(TestOtherActivity::class, 'other');

        $result = yield activity(TestHeartbeatActivity::class);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
