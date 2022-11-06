<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

final class TestFailingActivity extends Activity
{
    public function execute()
    {
        if ($this->attempts() === 1) {
            $this->storedWorkflow->toWorkflow()
                ->fail('failed');
        }

        return 'activity';
    }
}
