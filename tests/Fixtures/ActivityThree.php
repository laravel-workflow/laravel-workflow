<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

class ActivityThree extends Activity
{
    public function execute()
    {
        return 'three';
    }
}
