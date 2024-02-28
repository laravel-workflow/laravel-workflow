<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;
use Workflow\Workflow;

/**
 * @template TWorkflow of Workflow
 * @extends Activity<TWorkflow, string>
 */
final class TestHeartbeatActivity extends Activity
{
    public $timeout = 5;

    public function execute(): string
    {
        for ($i = 0; $i < 10; ++$i) {
            sleep(1);
            $this->heartbeat();
        }

        return 'activity';
    }
}
