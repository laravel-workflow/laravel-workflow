<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use Workflow\Activity;
use Workflow\Workflow;

/**
 * @template TWorkflow of Workflow
 * @extends Activity<TWorkflow, string>
 */
final class TestExceptionActivity extends Activity
{
    public function execute(): string
    {
        if ($this->attempts() === 1) {
            throw new Exception('failed');
        }

        return 'activity';
    }
}
