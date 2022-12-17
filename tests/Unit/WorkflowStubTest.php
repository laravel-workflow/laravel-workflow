<?php

declare(strict_types=1);

namespace Tests\Unit;

use Exception;
use Illuminate\Support\Carbon;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowFailedStatus;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;

final class WorkflowStubTest extends TestCase
{
    public function testMake(): void
    {
        Carbon::setTestNow('2022-01-01');

        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());

        $workflow->start();

        $workflow->cancel();

        while (! $workflow->isCanceled());

        $this->assertSame('2022-01-01 00:00:00', WorkflowStub::now()->toDateTimeString());

        $workflow = $workflow->fresh();

        $this->assertSame(WorkflowPendingStatus::class, $workflow->status());
        $this->assertNull($workflow->output());

        $workflow->fail(new Exception('test'));
        $this->assertSame(WorkflowFailedStatus::class, $workflow->status());

        $workflow->restart();
        $this->assertSame(WorkflowPendingStatus::class, $workflow->status());
    }
}
