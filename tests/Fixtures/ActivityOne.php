<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

class ActivityOne extends Activity
{
    public $tries = 1;

    public function execute($retried = false)
    {
        if (!$retried) {
            throw new \Exception("Intentional failure in ActivityOne");
        }

        return 'one';
    }
}