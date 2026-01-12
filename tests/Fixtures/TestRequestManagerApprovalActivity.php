<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

final class TestRequestManagerApprovalActivity extends Activity
{
    public function execute()
    {
        return 'manager_approval_requested';
    }
}
