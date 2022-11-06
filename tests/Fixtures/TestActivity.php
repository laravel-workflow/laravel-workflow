<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

final class TestActivity extends Activity
{
    public function execute()
    {
        return 'activity';
    }
}
