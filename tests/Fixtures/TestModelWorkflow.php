<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workbench\App\Models\User;
use Workflow\ActivityStub;
use Workflow\Workflow;

class TestModelWorkflow extends Workflow
{
    public function execute(User $user)
    {
        $result = yield ActivityStub::make(TestModelActivity::class, $user);

        return $result;
    }
}
