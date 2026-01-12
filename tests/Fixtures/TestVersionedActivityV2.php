<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

final class TestVersionedActivityV2 extends Activity
{
    public function execute(): string
    {
        return 'v2_result';
    }
}
