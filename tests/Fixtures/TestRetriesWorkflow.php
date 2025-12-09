<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\activity;
use Workflow\Workflow;

class TestRetriesWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute()
    {
        yield activity(TestRetriesActivity::class);
    }
}
