<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;

class TestYieldNonPromiseWorkflow extends Workflow
{
    public function execute()
    {
        yield 'not-a-promise';
    }
}
