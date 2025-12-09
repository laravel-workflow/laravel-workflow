<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\await;
use Workflow\SignalMethod;
use Workflow\Workflow;

final class TestPureAwaitWorkflow extends Workflow
{
    public bool $approved = false;

    #[SignalMethod]
    public function approve(bool $value): void
    {
        $this->approved = $value;
    }

    public function execute()
    {
        yield await(fn () => $this->approved);

        return 'approved';
    }
}
