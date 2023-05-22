<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

final class TestSagaUndoActivity extends Activity
{
    public function execute()
    {
        return 'saga_undo_activity';
    }
}
