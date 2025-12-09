<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workbench\App\Models\User;
use function Workflow\activity;
use Workflow\Workflow;

class TestModelWorkflow extends Workflow
{
    public function execute(User $user)
    {
        $result = yield activity(TestModelActivity::class, $user);

        return $result;
    }
}
