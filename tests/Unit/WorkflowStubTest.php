<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\Fixtures\TestActivity;
use Tests\Fixtures\TestOtherActivity;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Signal;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;

final class WorkflowStubTest extends TestCase
{
    public function testMake(): void
    {
        $workflow = WorkflowStub::make(TestWorkflow::class);

        $workflow->start();

        $workflow->cancel();

        while (! $workflow->isCanceled());

        $this->assertSame(WorkflowPendingStatus::class, $workflow->status());
    }
}
