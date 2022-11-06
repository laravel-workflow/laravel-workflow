<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

final class TestOtherActivity extends Activity
{
    public function execute($string)
    {
        return $string;
    }
}
