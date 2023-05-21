<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;

class TestTimeoutWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute()
    {
        yield ActivityStub::make(TestTimeoutActivity::class);
    }
}
