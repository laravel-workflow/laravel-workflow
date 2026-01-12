<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

final class TestRequestExecutiveApprovalActivity extends Activity
{
    public function execute()
    {
        return 'executive_approval_requested';
    }
}
