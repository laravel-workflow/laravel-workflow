<?php

namespace Tests\Fixtures;

use Workflow\Activity;

class TestHeartbeatActivity extends Activity
{
    public $timeout = 5;

    public function execute()
    {
        for ($i = 0; $i < 10; $i++) { 
            sleep(1);
            $this->heartbeat();
        }

        return 'activity';
    }
}
