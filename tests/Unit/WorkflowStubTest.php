<?php

declare(strict_types=1);

namespace Tests\Unit;

use Exception;
use Illuminate\Support\Carbon;
use Tests\Fixtures\TestAwaitWorkflow;
use Tests\Fixtures\TestBadConnectionWorkflow;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Y;
use Workflow\Signal;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;

final class WorkflowStubTest extends TestCase
{
    public function testMake(): void
    {
        Carbon::setTestNow('2022-01-01');

        $parentWorkflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedParentWorkflow = StoredWorkflow::findOrFail($parentWorkflow->id());
        $storedParentWorkflow->update([
            'arguments' => Y::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);

        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $workflow->start();
        $workflow->cancel();
        while (! $workflow->isCanceled());

        $workflow->fresh();
        $this->assertSame('2022-01-01 00:00:00', WorkflowStub::now()->toDateTimeString());
        $this->assertSame(WorkflowPendingStatus::class, $workflow->status());
        $this->assertNull($workflow->output());
        $this->assertSame(1, $workflow->logs()->count());

        $storedWorkflow->parents()
            ->attach($storedParentWorkflow, [
                'parent_index' => 0,
                'parent_now' => now(),
            ]);
        $workflow->fail(new Exception('test'));
        $this->assertTrue($workflow->failed());
        $this->assertTrue($parentWorkflow->failed());

        $workflow->cancel();
        while (! $workflow->isCanceled());

        $workflow->fresh();
        $this->assertSame(WorkflowPendingStatus::class, $workflow->status());
        $this->assertNull($workflow->output());
        $this->assertSame(2, $workflow->logs()->count());

        $this->assertSame('redis', WorkflowStub::connection());
        $this->assertSame('default', WorkflowStub::queue());
    }

    public function testComplete(): void
    {
        Carbon::setTestNow('2022-01-01');

        $workflow = WorkflowStub::make(TestAwaitWorkflow::class);
        $workflow->start();
        $workflow->cancel();
        $workflow->fail(new Exception('resume'));
        $workflow->resume();
        $this->assertSame('2022-01-01 00:00:00', WorkflowStub::now()->toDateTimeString());
        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow', $workflow->output());
        $this->assertSame(1, $workflow->exceptions()->count());
        $this->assertSame(1, $workflow->logs()->count());
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
        $workflow->fresh();

        $this->assertSame(2, $workflow->logs()->count());
        $this->assertSame(2, WorkflowStub::getContext()->index);
        $this->assertDatabaseHas('workflow_logs', [
            'stored_workflow_id' => $workflow->id(),
            'index' => 1,
            'class' => Signal::class,
        ]);
        $this->assertTrue(Y::unserialize($workflow->logs()->firstWhere('index', 1)->result));

        $workflow->fresh();
        $context = WorkflowStub::getContext();
        $context->index = 1;
        WorkflowStub::setContext($context);
        $promise = WorkflowStub::await(static fn () => true);

        $workflow->fresh();
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

        $promise = WorkflowStub::awaitWithTimeout(60, static fn () => false);

        $this->assertSame(1, $workflow->logs()->count());
        $this->assertSame(1, WorkflowStub::getContext()->index);

        $promise = WorkflowStub::awaitWithTimeout('1 minute', static fn () => true);
        $workflow->fresh();

        $this->assertSame(2, $workflow->logs()->count());
        $this->assertSame(2, WorkflowStub::getContext()->index);
        $this->assertDatabaseHas('workflow_logs', [
            'stored_workflow_id' => $workflow->id(),
            'index' => 1,
            'class' => Signal::class,
        ]);
        $this->assertTrue(Y::unserialize($workflow->logs()->firstWhere('index', 1)->result));

        $workflow->fresh();
        $context = WorkflowStub::getContext();
        $context->index = 1;
        WorkflowStub::setContext($context);
        $promise = WorkflowStub::awaitWithTimeout('1 minute', static fn () => true);

        $workflow->fresh();
        $this->assertSame(2, $workflow->logs()->count());
        $this->assertSame(2, WorkflowStub::getContext()->index);
    }

    public function testAwaitWithTimeoutTimedout(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $workflow->start();
        $workflow->cancel();
        while (! $workflow->isCanceled());

        $this->assertSame(1, $workflow->logs()->count());
        $this->assertSame(1, WorkflowStub::getContext()->index);

        $workflow = WorkflowStub::load($workflow->id());
        $context = WorkflowStub::getContext();
        $context->index = 1;
        WorkflowStub::setContext($context);

        $promise = WorkflowStub::awaitWithTimeout('1 minute', static fn () => false);

        $this->assertSame(1, $workflow->logs()->count());
        $this->assertSame(2, WorkflowStub::getContext()->index);

        $workflow = WorkflowStub::load($workflow->id());
        $context = WorkflowStub::getContext();
        $context->index = 1;
        $context->now = $context->now->addMinute();
        WorkflowStub::setContext($context);

        $promise = WorkflowStub::awaitWithTimeout('1 minute', static fn () => false);

        $this->assertSame(2, $workflow->logs()->count());
        $this->assertSame(2, WorkflowStub::getContext()->index);
        $this->assertDatabaseHas('workflow_logs', [
            'stored_workflow_id' => $workflow->id(),
            'index' => 1,
            'class' => Signal::class,
        ]);
        $this->assertTrue(Y::unserialize($workflow->logs()->firstWhere('index', 1)->result));
    }

    public function testConnection(): void
    {
        Carbon::setTestNow('2022-01-01');

        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $otherWorkflow = WorkflowStub::load(WorkflowStub::make(TestBadConnectionWorkflow::class)->id());

        $workflow->cancel();

        $this->assertSame('redis', WorkflowStub::connection());
        $this->assertSame('default', WorkflowStub::queue());
    }
}
