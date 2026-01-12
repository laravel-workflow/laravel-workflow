<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\SignalMethod;
use Workflow\Workflow;
use function Workflow\{activity, await};

final class TestActivityAwaitActivityAwaitWorkflow extends Workflow
{
    public bool $firstApproved = false;

    public bool $secondApproved = false;

    #[SignalMethod]
    public function approveFirst(bool $value): void
    {
        $this->firstApproved = $value;
    }

    #[SignalMethod]
    public function approveSecond(bool $value): void
    {
        $this->secondApproved = $value;
    }

    public function execute()
    {
        yield activity(TestActivity::class);

        yield await(fn () => $this->firstApproved);

        yield activity(TestActivity::class);

        yield await(fn () => $this->secondApproved);

        return 'completed';
    }
}
