<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

final class TestRequestFinanceApprovalActivity extends Activity
{
    public function execute()
    {
        return 'finance_approval_requested';
    }
}
