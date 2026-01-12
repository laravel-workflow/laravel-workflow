<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use Workflow\Activity;

final class TestSingleTryExceptionActivity extends Activity
{
    public $tries = 1;

    public function execute($shouldThrow)
    {
        if ($shouldThrow) {
            throw new Exception('failed');
        }

        return 'activity';
    }
}
