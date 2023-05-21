<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

class TestUndoActivity extends Activity
{
    public function execute()
    {
        return 'undo_activity';
    }
}
