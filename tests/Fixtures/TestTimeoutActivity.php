<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Generator;
use Workflow\Activity;
use Workflow\Workflow;

/**
 * @template TWorkflow of Workflow
 * @extends Activity<TWorkflow, void>
 */
final class TestTimeoutActivity extends Activity
{
    public $timeout = 3;

    public $tries = 1;

    public function execute(): void
    {
        sleep(PHP_INT_MAX);
    }
}
