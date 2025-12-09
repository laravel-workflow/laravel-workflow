<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\await;
use Workflow\SignalMethod;
use Workflow\Workflow;

final class TestAwaitWorkflow extends Workflow
{
    private bool $canceled = false;

    #[SignalMethod]
    public function cancel(): void
    {
        $this->canceled = true;
    }

    public function execute()
    {
        yield await(fn (): bool => $this->canceled);

        return 'workflow';
    }
}
