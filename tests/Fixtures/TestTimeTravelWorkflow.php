<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\QueryMethod;
use Workflow\SignalMethod;
use Workflow\Workflow;
use function Workflow\{activity, await, minutes};

class TestTimeTravelWorkflow extends Workflow
{
    private bool $canceled = false;

    #[SignalMethod]
    public function cancel(): void
    {
        $this->canceled = true;
    }

    #[QueryMethod]
    public function isCanceled(): bool
    {
        return $this->canceled;
    }

    public function execute()
    {
        $otherResult = yield activity(TestOtherActivity::class, 'other');

        yield await(fn (): bool => $this->canceled);

        $result = yield activity(TestActivity::class);

        yield minutes(1);

        return 'workflow_' . $result . '_' . $otherResult;
    }
}
