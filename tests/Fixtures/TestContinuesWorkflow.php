<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowStub;

final class TestContinuesWorkflow extends Workflow
{
    private const CONTINUE_AS_NEW_FREQUENCY = 2;

    public function execute($count = 0)
    {
        for ($i = 0; $i < self::CONTINUE_AS_NEW_FREQUENCY; $i++) {
            $count++;
            $results[] = yield ActivityStub::make(TestOtherActivity::class, (string) $count);
        }
        if ($count >= 10) {
            return $results;
        }
        return WorkflowStub::continueAsNew($count);
    }
}
