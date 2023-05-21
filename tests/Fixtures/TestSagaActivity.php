<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use Workflow\Activity;

final class TestSagaActivity extends Activity
{
    public $tries = 1;

    public function execute()
    {
        throw new Exception('saga');
    }
}
