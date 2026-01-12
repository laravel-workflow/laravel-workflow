<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\activity;
use Workflow\Workflow;

class TestChildWorkflow extends Workflow
{
    public function execute()
    {
        $otherResult = yield activity(TestOtherActivity::class, 'other');

        return $otherResult;
    }
}
