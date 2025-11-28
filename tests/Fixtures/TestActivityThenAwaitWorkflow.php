<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Tests\Fixtures\TestActivity;
use Workflow\ActivityStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

/**
 * Case 2: Activity then await
 */
final class TestActivityThenAwaitWorkflow extends Workflow
{
    public bool $approved = false;

    #[SignalMethod]
    public function approve(bool $value): void
    {
        $this->approved = $value;
    }

    public function execute()
    {
        yield ActivityStub::make(TestActivity::class);

        yield WorkflowStub::await(fn () => $this->approved);

        return 'approved';
    }
}
