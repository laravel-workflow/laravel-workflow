<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\await;
use Workflow\SignalMethod;
use Workflow\Workflow;

class TestSimpleChildWorkflowWithSignal extends Workflow
{
    private ?string $approved = null;

    #[SignalMethod]
    public function approve(string $status): void
    {
        $this->approved = $status;
    }

    public function execute(string $prefix)
    {
        yield await(fn () => $this->approved !== null);

        return $prefix . '_' . $this->approved;
    }
}
