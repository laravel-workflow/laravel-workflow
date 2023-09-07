<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Illuminate\Contracts\Foundation\Application;
use Workflow\Activity;

final class TestOtherActivity extends Activity
{
    public function execute(Application $app, $string)
    {
        assert($app->runningInConsole());

        return $string;
    }
}
