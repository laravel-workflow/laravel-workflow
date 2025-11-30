<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Tests\Fixtures\TestChildWorkflow;
use Tests\Fixtures\TestParentWorkflow;
use Tests\Fixtures\TestSimpleChildWorkflowWithSignal;
use Tests\TestCase;
use Workflow\ChildWorkflowHandle;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;

final class ChildWorkflowHandleTest extends TestCase
{
    public function testId(): void
    {
        $storedWorkflow = StoredWorkflow::create([
            'class' => TestChildWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $handle = new ChildWorkflowHandle($storedWorkflow);

        $this->assertSame($storedWorkflow->id, $handle->id());
    }

    public function testCallMethodSignalsChildWorkflow(): void
    {
        Carbon::setTestNow('2022-01-01');

        $storedParentWorkflow = StoredWorkflow::create([
            'class' => TestParentWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $storedChildWorkflow = StoredWorkflow::create([
            'class' => TestSimpleChildWorkflowWithSignal::class,
            'arguments' => Serializer::serialize(['test']),
            'status' => WorkflowPendingStatus::class,
        ]);

        WorkflowStub::setContext([
            'storedWorkflow' => $storedParentWorkflow,
            'index' => 0,
            'now' => Carbon::now(),
            'replaying' => false,
        ]);

        $handle = new ChildWorkflowHandle($storedChildWorkflow);

        $handle->approve('approved');

        $context = WorkflowStub::getContext();
        $this->assertSame($storedParentWorkflow->id, $context->storedWorkflow->id);
        $this->assertSame(0, $context->index);
        $this->assertFalse($context->replaying);

        $signal = $storedChildWorkflow->signals()
            ->where('method', 'approve')
            ->first();
        $this->assertNotNull($signal);
        $this->assertSame('approve', $signal->method);
        $this->assertSame(['approved'], Serializer::unserialize($signal->arguments));
    }

    public function testCallMethodSkipsWhenReplaying(): void
    {
        Carbon::setTestNow('2022-01-01');

        $parentWorkflow = WorkflowStub::make(TestParentWorkflow::class);
        $storedParentWorkflow = StoredWorkflow::findOrFail($parentWorkflow->id());
        $storedParentWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $storedChildWorkflow = StoredWorkflow::create([
            'class' => TestSimpleChildWorkflowWithSignal::class,
            'arguments' => Serializer::serialize(['test']),
            'status' => WorkflowPendingStatus::class,
        ]);

        WorkflowStub::setContext([
            'storedWorkflow' => $storedParentWorkflow,
            'index' => 0,
            'now' => Carbon::now(),
            'replaying' => true,
        ]);

        $handle = new ChildWorkflowHandle($storedChildWorkflow);

        $result = $handle->approve('approved');

        $this->assertNull($result);

        $this->assertSame(0, $storedChildWorkflow->signals()->count());
    }

    public function testChildMethodReturnsNullWhenNoChildren(): void
    {
        $storedWorkflow = StoredWorkflow::create([
            'class' => TestParentWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $workflow = new TestParentWorkflow($storedWorkflow);

        $this->assertNull($workflow->child());
    }

    public function testChildMethodReturnsChildHandle(): void
    {
        Carbon::setTestNow('2022-01-01');

        $storedParentWorkflow = StoredWorkflow::create([
            'class' => TestParentWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $storedChildWorkflow = StoredWorkflow::create([
            'class' => TestChildWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $storedParentWorkflow->children()
            ->attach($storedChildWorkflow, [
                'parent_index' => 0,
                'parent_now' => Carbon::now(),
            ]);

        $workflow = new TestParentWorkflow($storedParentWorkflow);

        $childHandle = $workflow->child();

        $this->assertInstanceOf(ChildWorkflowHandle::class, $childHandle);
        $this->assertSame($storedChildWorkflow->id, $childHandle->id());
    }

    public function testChildMethodRespectsIndex(): void
    {
        Carbon::setTestNow('2022-01-01');

        $storedParentWorkflow = StoredWorkflow::create([
            'class' => TestParentWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $storedChildWorkflow1 = StoredWorkflow::create([
            'class' => TestChildWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);
        $storedParentWorkflow->children()
            ->attach($storedChildWorkflow1, [
                'parent_index' => 0,
                'parent_now' => Carbon::now(),
            ]);

        $storedChildWorkflow2 = StoredWorkflow::create([
            'class' => TestChildWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);
        $storedParentWorkflow->children()
            ->attach($storedChildWorkflow2, [
                'parent_index' => 5,
                'parent_now' => Carbon::now(),
            ]);

        $workflow = new TestParentWorkflow($storedParentWorkflow);

        $workflow->index = 0;
        $childHandle = $workflow->child();
        $this->assertSame($storedChildWorkflow1->id, $childHandle->id());

        $workflow->index = 5;
        $childHandle = $workflow->child();
        $this->assertSame($storedChildWorkflow2->id, $childHandle->id());
    }

    public function testChildrenMethodReturnsEmptyArrayWhenNoChildren(): void
    {
        $storedWorkflow = StoredWorkflow::create([
            'class' => TestParentWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $workflow = new TestParentWorkflow($storedWorkflow);

        $this->assertSame([], $workflow->children());
    }

    public function testChildrenMethodReturnsAllChildHandles(): void
    {
        Carbon::setTestNow('2022-01-01');

        $storedParentWorkflow = StoredWorkflow::create([
            'class' => TestParentWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $storedChildWorkflow1 = StoredWorkflow::create([
            'class' => TestChildWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);
        $storedParentWorkflow->children()
            ->attach($storedChildWorkflow1, [
                'parent_index' => 0,
                'parent_now' => Carbon::now(),
            ]);

        $storedChildWorkflow2 = StoredWorkflow::create([
            'class' => TestChildWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);
        $storedParentWorkflow->children()
            ->attach($storedChildWorkflow2, [
                'parent_index' => 1,
                'parent_now' => Carbon::now(),
            ]);

        $workflow = new TestParentWorkflow($storedParentWorkflow);
        $workflow->index = 10;

        $children = $workflow->children();

        $this->assertCount(2, $children);
        $this->assertInstanceOf(ChildWorkflowHandle::class, $children[0]);
        $this->assertInstanceOf(ChildWorkflowHandle::class, $children[1]);
    }

    public function testChildrenMethodRespectsIndex(): void
    {
        Carbon::setTestNow('2022-01-01');

        $storedParentWorkflow = StoredWorkflow::create([
            'class' => TestParentWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);

        $storedChildWorkflow1 = StoredWorkflow::create([
            'class' => TestChildWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);
        $storedParentWorkflow->children()
            ->attach($storedChildWorkflow1, [
                'parent_index' => 0,
                'parent_now' => Carbon::now(),
            ]);

        $storedChildWorkflow2 = StoredWorkflow::create([
            'class' => TestChildWorkflow::class,
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::class,
        ]);
        $storedParentWorkflow->children()
            ->attach($storedChildWorkflow2, [
                'parent_index' => 5,
                'parent_now' => Carbon::now(),
            ]);

        $workflow = new TestParentWorkflow($storedParentWorkflow);

        $workflow->index = 0;
        $children = $workflow->children();
        $this->assertCount(1, $children);
        $this->assertSame($storedChildWorkflow1->id, $children[0]->id());

        $workflow->index = 5;
        $children = $workflow->children();
        $this->assertCount(2, $children);
    }
}
