<?php

namespace Tests;

use Workflow\Activity;

class TestOtherActivity extends Activity
{
    public function execute($string)
    {
        return $string;
    }
}
