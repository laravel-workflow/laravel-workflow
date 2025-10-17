<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workbench\App\Models\User;
use Workflow\Activity;

class TestModelActivity extends Activity
{
    public function execute(User $user)
    {
        return $user->id;
    }
}
