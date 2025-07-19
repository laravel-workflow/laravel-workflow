<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

class TestCountActivity extends Activity
{
    public function execute(int $count)
    {
        return $count;
    }
}
