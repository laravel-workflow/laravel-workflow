<?php

declare(strict_types=1);

namespace Tests\Unit;

use BadMethodCallException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\TestActivity;
use Tests\Fixtures\TestChildWorkflow;
use Tests\Fixtures\TestOtherActivity;
use Tests\Fixtures\TestParentWorkflow;
use Tests\Fixtures\TestThrowOnReturnWorkflow;
use Tests\Fixtures\TestWorkflow;
use Tests\Fixtures\TestYieldNonPromiseWorkflow;
use Tests\TestCase;
use Workflow\Events\WorkflowFailed;
use Workflow\Exception;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\States\WorkflowPendingStatus;
use Workflow\Workflow;
use Workflow\WorkflowStub;

final class WorkflowTest extends TestCase
{
    public function testFailed(): void
    {
        Event::fake();

        $parentWorkflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedParentWorkflow = StoredWorkflow::findOrFail($parentWorkflow->id());
        $storedParentWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);

        $stub = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($stub->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $storedWorkflow->parents()
            ->attach($storedParentWorkflow, [
                'parent_index' => 0,
                'parent_now' => now(),
            ]);

        $workflow = new Workflow($storedWorkflow);

        $workflow->failed(new \Exception('Test exception'));

        $this->assertSame(WorkflowFailedStatus::class, $stub->status());
        $this->assertSame(
            'Test exception',
            Serializer::unserialize($stub->exceptions()->first()->exception)['message']
        );

        Event::assertDispatched(WorkflowFailed::class, static function ($event) use ($stub) {
            return $event->workflowId === $stub->id();
        });
    }

    public function testFailedTwice(): void
    {
        Event::fake();

        $stub = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($stub->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowFailedStatus::class,
        ]);

        $workflow = new Workflow($storedWorkflow);

        $workflow->failed(new \Exception('Test exception'));

        $this->assertSame(WorkflowFailedStatus::class, $stub->status());
        $this->assertSame(
            'Test exception',
            Serializer::unserialize($stub->exceptions()->first()->exception)['message']
        );

        Event::assertNotDispatched(WorkflowFailed::class, static function ($event) use ($stub) {
            return $event->workflowId === $stub->id();
        });
    }

    public function testFailedWithParentFailed(): void
    {
        Event::fake();

        $parentWorkflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedParentWorkflow = StoredWorkflow::findOrFail($parentWorkflow->id());
        $storedParentWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowFailedStatus::$name,
        ]);

        $stub = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($stub->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $storedWorkflow->parents()
            ->attach($storedParentWorkflow, [
                'parent_index' => 0,
                'parent_now' => now(),
            ]);

        $workflow = new Workflow($storedWorkflow);

        $workflow->failed(new \Exception('Test exception'));

        $this->assertSame(WorkflowFailedStatus::class, $stub->status());
        $this->assertSame(
            'Test exception',
            Serializer::unserialize($stub->exceptions()->first()->exception)['message']
        );

        Event::assertDispatched(WorkflowFailed::class, static function ($event) use ($stub) {
            return $event->workflowId === $stub->id();
        });
    }

    public function testException(): void
    {
        $exception = new \Exception('test');
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->arguments = Serializer::serialize([]);
        $storedWorkflow->save();
        $activity = new Exception(0, now()->toDateTimeString(), StoredWorkflow::findOrFail(
            $workflow->id()
        ), $exception);

        $activity->handle();

        $this->assertSame(Exception::class, $storedWorkflow->logs()->first()->class);
    }

    public function testExceptionAlreadyLogged(): void
    {
        $exception = new \Exception('test');
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->arguments = Serializer::serialize([]);
        $storedWorkflow->save();
        $activity = new Exception(0, now()->toDateTimeString(), StoredWorkflow::findOrFail(
            $workflow->id()
        ), $exception);

        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => TestOtherActivity::class,
                'result' => Serializer::serialize($exception),
            ]);

        $activity->handle();

        $this->assertSame(1, $storedWorkflow->logs()->count());
    }

    public function testParent(): void
    {
        Carbon::setTestNow('2022-01-01');

        $parentWorkflow = WorkflowStub::load(WorkflowStub::make(TestParentWorkflow::class)->id());

        $storedParentWorkflow = StoredWorkflow::findOrFail($parentWorkflow->id());
        $storedParentWorkflow->arguments = Serializer::serialize([]);
        $storedParentWorkflow->save();

        $storedParentWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => now(),
                'class' => TestChildWorkflow::class,
                'result' => Serializer::serialize('child_workflow'),
            ]);

        $storedParentWorkflow->logs()
            ->create([
                'index' => 1,
                'now' => now(),
                'class' => TestActivity::class,
                'result' => Serializer::serialize('activity'),
            ]);

        $childWorkflow = WorkflowStub::load(WorkflowStub::make(TestChildWorkflow::class)->id());

        $storedChildWorkflow = StoredWorkflow::findOrFail($childWorkflow->id());
        $storedChildWorkflow->arguments = Serializer::serialize([]);
        $storedChildWorkflow->status = WorkflowPendingStatus::class;
        $storedChildWorkflow->save();
        $storedChildWorkflow->parents()
            ->attach($storedParentWorkflow, [
                'parent_index' => 0,
                'parent_now' => now(),
            ]);

        $storedChildWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => now(),
                'class' => TestOtherActivity::class,
                'result' => Serializer::serialize('other'),
            ]);

        (new (TestChildWorkflow::class)($storedChildWorkflow))->handle();
        (new (TestParentWorkflow::class)($storedParentWorkflow))->handle();

        $this->assertSame('2022-01-01 00:00:00', WorkflowStub::now()->toDateTimeString());
        $this->assertSame(WorkflowCompletedStatus::class, $childWorkflow->status());
        $this->assertSame('other', $childWorkflow->output());
    }

    public function testParentPending(): void
    {
        Carbon::setTestNow('2022-01-01');

        $parentWorkflow = WorkflowStub::load(WorkflowStub::make(TestParentWorkflow::class)->id());

        $storedParentWorkflow = StoredWorkflow::findOrFail($parentWorkflow->id());
        $storedParentWorkflow->arguments = Serializer::serialize([]);
        $storedParentWorkflow->status = WorkflowPendingStatus::class;
        $storedParentWorkflow->save();

        $storedParentWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => now(),
                'class' => TestChildWorkflow::class,
                'result' => Serializer::serialize('child_workflow'),
            ]);

        $storedParentWorkflow->logs()
            ->create([
                'index' => 1,
                'now' => now(),
                'class' => TestActivity::class,
                'result' => Serializer::serialize('activity'),
            ]);

        $childWorkflow = WorkflowStub::load(WorkflowStub::make(TestChildWorkflow::class)->id());

        $storedChildWorkflow = StoredWorkflow::findOrFail($childWorkflow->id());
        $storedChildWorkflow->arguments = Serializer::serialize([]);
        $storedChildWorkflow->status = WorkflowPendingStatus::class;
        $storedChildWorkflow->save();
        $storedChildWorkflow->parents()
            ->attach($storedParentWorkflow, [
                'parent_index' => 0,
                'parent_now' => now(),
            ]);

        $storedChildWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => now(),
                'class' => TestOtherActivity::class,
                'result' => Serializer::serialize('other'),
            ]);

        (new (TestChildWorkflow::class)($storedChildWorkflow))->handle();
        (new (TestParentWorkflow::class)($storedParentWorkflow))->handle();

        $this->assertSame('2022-01-01 00:00:00', WorkflowStub::now()->toDateTimeString());
        $this->assertSame(WorkflowCompletedStatus::class, $childWorkflow->status());
        $this->assertSame('other', $childWorkflow->output());
    }

    public function testThrowsWhenExecuteMethodIsMissing(): void
    {
        $stub = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($stub->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Execute method not implemented.');

        $workflow = new Workflow($storedWorkflow);
        $workflow->handle();
    }

    public function testThrowsWhenYieldNonPromise(): void
    {
        $stub = WorkflowStub::load(WorkflowStub::make(TestYieldNonPromiseWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($stub->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('something went wrong');

        $workflow = new TestYieldNonPromiseWorkflow($storedWorkflow);
        $workflow->handle();
    }

    public function testThrowsWrappedException(): void
    {
        $stub = WorkflowStub::load(WorkflowStub::make(TestThrowOnReturnWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($stub->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Workflow failed.');

        $workflow = new TestThrowOnReturnWorkflow($storedWorkflow);
        $workflow->handle();
    }
}
