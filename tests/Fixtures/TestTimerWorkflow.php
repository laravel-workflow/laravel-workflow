<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\timer;
use Workflow\Workflow;

final class TestTimerWorkflow extends Workflow
{
    public function execute($seconds = 1)
    {
        yield timer($seconds);

        return 'workflow';
    }
}
