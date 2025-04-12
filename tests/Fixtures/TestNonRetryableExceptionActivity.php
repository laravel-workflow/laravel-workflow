<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;
use Workflow\Exceptions\NonRetryableException;

final class TestNonRetryableExceptionActivity extends Activity
{
    public function execute()
    {
        throw new NonRetryableException('This is a non-retryable error');
    }
}
