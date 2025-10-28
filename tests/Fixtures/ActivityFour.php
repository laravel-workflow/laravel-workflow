<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

class ActivityFour extends Activity
{
    public function execute($data)
    {
        return 'four with ' . json_encode($data);
    }
}
