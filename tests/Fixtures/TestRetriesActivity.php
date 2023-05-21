<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use Workflow\Activity;

final class TestRetriesActivity extends Activity
{
    public $tries = 3;

    public function execute()
    {
        throw new Exception('failed');
    }
}
