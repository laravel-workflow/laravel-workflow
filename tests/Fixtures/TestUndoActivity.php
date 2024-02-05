<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;
use Workflow\Workflow;

/**
 * @template TWorkflow of Workflow
 * @extends Activity<TWorkflow, string>
 */
class TestUndoActivity extends Activity
{
    public function execute(): string
    {
        return 'undo_activity';
    }
}
