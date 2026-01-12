<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use function Workflow\{activity, timer};

class TestChildTimerWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute($seconds = 1)
    {
        $otherResult = yield activity(TestOtherActivity::class, 'other');

        yield timer($seconds);

        return $otherResult;
    }
}
