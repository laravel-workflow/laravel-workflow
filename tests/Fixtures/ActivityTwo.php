<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

class ActivityTwo extends Activity
{
    public $tries = 1;

    public function execute($data)
    {
        return 'two with ' . json_encode($data);
    }
}
