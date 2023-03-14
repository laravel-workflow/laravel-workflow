<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\Fixtures\TestChildWorkflow;
use Tests\Fixtures\TestParentWorkflow;
use Tests\TestCase;
use Workflow\ChildWorkflowStub;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Y;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;

final class ChildWorkflowStubTest extends TestCase
{
    public function testStoresResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestParentWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Y::serialize([]),
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
            'arguments' => Y::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => TestParentWorkflow::class,
                'result' => Y::serialize('test'),
            ]);

        ChildWorkflowStub::make(TestChildWorkflow::class)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertSame('test', $result);
    }

    public function testAll(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestParentWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Y::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => TestParentWorkflow::class,
                'result' => Y::serialize('test'),
            ]);

        ChildWorkflowStub::all([ChildWorkflowStub::make(TestChildWorkflow::class)])
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertSame(['test'], $result);
    }
}
