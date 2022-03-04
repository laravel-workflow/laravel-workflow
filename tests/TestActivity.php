<?php

namespace Tests;

use Workflow\Activity;

class TestActivity extends Activity
{
    public function execute()
    {
        return 'activity';
    }
}
