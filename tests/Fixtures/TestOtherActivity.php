<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Generator;
use Illuminate\Contracts\Foundation\Application;
use Workflow\Activity;
use Workflow\Workflow;

/**
 * @template TWorkflow of Workflow
 * @extends Activity<TWorkflow, string>
 */
final class TestOtherActivity extends Activity
{
    public function execute(Application $app, mixed $string): mixed
    {
        assert($app->runningInConsole());

        return $string;
    }
}
