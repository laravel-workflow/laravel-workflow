<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Activity;

final class TestRequestLegalApprovalActivity extends Activity
{
    public function execute()
    {
        return 'legal_approval_requested';
    }
}
