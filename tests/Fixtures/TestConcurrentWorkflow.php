<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use function Workflow\{activity, all};

final class TestConcurrentWorkflow extends Workflow
{
    public function execute()
    {
        $otherResultPromise = activity(TestOtherActivity::class, 'other');

        $resultPromise = activity(TestActivity::class);

        $results = yield all([$otherResultPromise, $resultPromise]);

        return 'workflow_' . $results[1] . '_' . $results[0];
    }
}
