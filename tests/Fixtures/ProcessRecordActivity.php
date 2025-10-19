<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

class ProcessRecordActivity extends Activity
{
    public function execute(): array
    {
        return [
            'status' => 'done',
        ];
    }
}
