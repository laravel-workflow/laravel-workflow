<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;

class TestThrowOnReturnWorkflow extends Workflow
{
    public function execute()
    {
        return new class() {
            public function valid()
            {
                return false;
            }
        };
    }
}
