<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;
use Workflow\Workflow;

/**
 * @template TWorkflow of Workflow
 * @extends Activity<TWorkflow, mixed>
 */
class TestInvalidActivity extends Activity
{
}
