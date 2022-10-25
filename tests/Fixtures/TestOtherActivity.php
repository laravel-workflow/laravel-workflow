<?php

namespace Tests\Fixtures;

use Workflow\Activity;

class TestOtherActivity extends Activity
{
    public function execute($string)
    {
        return $string;
    }
}
