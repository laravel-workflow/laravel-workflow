<?php

namespace Tests;

use Exception;
use Workflow\Activity;

class TestFailingActivity extends Activity
{
    public function execute()
    {
        throw new Exception('failed');
    }
}
