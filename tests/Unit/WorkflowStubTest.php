<?php

declare(strict_types=1);

namespace Tests\Unit;

use Exception;
use Illuminate\Support\Carbon;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Serializers\Y;
use Workflow\Signal;
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

    public function testAwait(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $workflow->start();
        $workflow->cancel();
        while (! $workflow->isCanceled());

        $workflow = WorkflowStub::load($workflow->id());

        $this->assertSame(0, WorkflowStub::getContext()->index);

        $promise = WorkflowStub::await(static fn () => false);

        $this->assertSame(1, $workflow->logs()->count());
        $this->assertSame(1, WorkflowStub::getContext()->index);

        $promise = WorkflowStub::await(static fn () => true);
        $worklow = $workflow->fresh();

        $this->assertSame(2, $workflow->logs()->count());
        $this->assertSame(2, WorkflowStub::getContext()->index);
        $this->assertDatabaseHas('workflow_logs', [
            'stored_workflow_id' => $workflow->id(),
            'index' => 1,
            'class' => Signal::class,
            'result' => Y::serialize(true),
        ]);

        $worklow = $workflow->fresh();
        $context = WorkflowStub::getContext();
        $context->index = 1;
        WorkflowStub::setContext($context);
        $promise = WorkflowStub::await(static fn () => true);

        $worklow = $workflow->fresh();
        $this->assertSame(2, $workflow->logs()->count());
        $this->assertSame(2, WorkflowStub::getContext()->index);
    }

    public function testAwaitWithTimeout(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $workflow->start();
        $workflow->cancel();
        while (! $workflow->isCanceled());

        $workflow = WorkflowStub::load($workflow->id());

        $this->assertSame(0, WorkflowStub::getContext()->index);

        $promise = WorkflowStub::awaitWithTimeout('1 minute', static fn () => false);

        $this->assertSame(1, $workflow->logs()->count());
        $this->assertSame(1, WorkflowStub::getContext()->index);

        $promise = WorkflowStub::awaitWithTimeout('1 minute', static fn () => true);
        $worklow = $workflow->fresh();

        $this->assertSame(2, $workflow->logs()->count());
        $this->assertSame(2, WorkflowStub::getContext()->index);
        $this->assertDatabaseHas('workflow_logs', [
            'stored_workflow_id' => $workflow->id(),
            'index' => 1,
            'class' => Signal::class,
            'result' => Y::serialize(true),
        ]);

        $worklow = $workflow->fresh();
        $context = WorkflowStub::getContext();
        $context->index = 1;
        WorkflowStub::setContext($context);
        $promise = WorkflowStub::awaitWithTimeout('1 minute', static fn () => true);

        $worklow = $workflow->fresh();
        $this->assertSame(2, $workflow->logs()->count());
        $this->assertSame(2, WorkflowStub::getContext()->index);
    }
}
