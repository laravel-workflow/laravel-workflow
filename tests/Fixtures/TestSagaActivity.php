<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use Workflow\Activity;
use Workflow\Workflow;

/**
 * @template TWorkflow of Workflow
 * @extends Activity<TWorkflow, void>
 */
final class TestSagaActivity extends Activity
{
    public $tries = 1;

    public function execute(): void
    {
        throw new Exception('saga');
    }
}
