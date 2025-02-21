<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Models\StoredWorkflowLog;
use Workflow\Workflow;

class TestModelNotFoundWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute(StoredWorkflowLog $log)
    {
    }
}
