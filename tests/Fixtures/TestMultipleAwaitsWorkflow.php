<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\await;
use Workflow\SignalMethod;
use Workflow\Workflow;

final class TestMultipleAwaitsWorkflow extends Workflow
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
        yield await(fn () => $this->firstApproved);

        yield await(fn () => $this->secondApproved);

        return 'both_approved';
    }
}
