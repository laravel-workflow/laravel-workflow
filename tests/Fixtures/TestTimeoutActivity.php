<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use Workflow\Activity;

final class TestTimeoutActivity extends Activity
{
    public $timeout = 3;
    public $tries = 1;

    public function execute()
    {
        sleep(PHP_INT_MAX);
    }
}
