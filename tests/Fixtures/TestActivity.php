<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

class TestActivity extends Activity
{
    public function execute()
    {
        return 'activity';
    }
}
