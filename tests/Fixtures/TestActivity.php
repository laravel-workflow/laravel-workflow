<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\Foundation\Application;
use Workflow\Activity;

class TestActivity extends Activity
{
    public function execute(Application $app)
    {
        assert($app->runningInConsole());

        return 'activity';
    }
}
