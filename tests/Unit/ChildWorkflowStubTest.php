<?php

declare(strict_types=1);

namespace Tests\Unit;

use Mockery;
use Tests\Fixtures\TestChildWorkflow;
use Tests\Fixtures\TestParentWorkflow;
use Tests\TestCase;
use Workflow\ChildWorkflowStub;
use Workflow\Exceptions\TransitionNotFound;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;

final class ChildWorkflowStubTest extends TestCase
{
    public function testStoresResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestParentWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);

        ChildWorkflowStub::make(TestChildWorkflow::class)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertNull($result);
        $this->assertSame(WorkflowPendingStatus::class, $workflow->status());
        $this->assertNull($workflow->output());
    }

    public function testLoadsStoredResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestParentWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => TestParentWorkflow::class,
                'result' => Serializer::serialize('test'),
            ]);

        ChildWorkflowStub::make(TestChildWorkflow::class)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertSame('test', $result);
    }

    public function testLoadsChildWorkflow(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestParentWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);

        $childWorkflow = WorkflowStub::load(WorkflowStub::make(TestChildWorkflow::class)->id());
        $storedChildWorkflow = StoredWorkflow::findOrFail($childWorkflow->id());
        $storedChildWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);
        $storedChildWorkflow->parents()
            ->attach($storedWorkflow, [
                'parent_index' => 0,
                'parent_now' => now(),
            ]);

        $workflow = $storedWorkflow->toWorkflow();

        $existingChildWorkflow = ChildWorkflowStub::make(TestChildWorkflow::class)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertNull($result);
    }

    public function testIgnoresTransitionNotFoundWhenChildResumeThrows(): void
    {
        $childWorkflow = new class() {
            public function running(): bool
            {
                return true;
            }

            public function created(): bool
            {
                return false;
            }

            public function resume(): void
            {
                throw TransitionNotFound::make('running', 'pending', StoredWorkflow::class);
            }

            public function completed(): bool
            {
                return false;
            }

            public function startAsChild(...$arguments): void
            {
            }
        };

        $storedChildWorkflow = Mockery::mock();
        $storedChildWorkflow->shouldReceive('toWorkflow')
            ->once()
            ->andReturn($childWorkflow);

        $children = Mockery::mock();
        $children->shouldReceive('wherePivot')
            ->once()
            ->with('parent_index', 0)
            ->andReturnSelf();
        $children->shouldReceive('first')
            ->once()
            ->andReturn($storedChildWorkflow);

        $storedWorkflow = Mockery::mock();
        $storedWorkflow->shouldReceive('findLogByIndex')
            ->once()
            ->with(0)
            ->andReturn(null);
        $storedWorkflow->shouldReceive('children')
            ->once()
            ->andReturn($children);
        $storedWorkflow->shouldReceive('effectiveConnection')
            ->once()
            ->andReturn(null);
        $storedWorkflow->shouldReceive('effectiveQueue')
            ->once()
            ->andReturn(null);

        WorkflowStub::setContext([
            'storedWorkflow' => $storedWorkflow,
            'index' => 0,
            'now' => now(),
            'replaying' => false,
        ]);

        ChildWorkflowStub::make(TestChildWorkflow::class);

        $this->assertSame(1, WorkflowStub::getContext()->index);
    }

    public function testAll(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestParentWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => TestParentWorkflow::class,
                'result' => Serializer::serialize('test'),
            ]);

        ChildWorkflowStub::all([ChildWorkflowStub::make(TestChildWorkflow::class)])
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertSame(['test'], $result);
    }
}
