<?php

namespace Tests\Fixtures;

use Exception;
use Workflow\Activity;

class TestExceptionActivity extends Activity
{
    public function execute()
    {
        if ($this->attempts() === 1) {
            throw new Exception('failed');
        }

        return 'activity';
    }
}
