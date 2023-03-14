<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Tests\Fixtures\TestChildWorkflow;
use Tests\Fixtures\TestParentWorkflow;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Y;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;

final class WorkflowTest extends TestCase
{
    public function testParent(): void
    {
        Carbon::setTestNow('2022-01-01');

        $parentWorkflow = WorkflowStub::load(WorkflowStub::make(TestParentWorkflow::class)->id());

        $storedParentWorkflow = StoredWorkflow::findOrFail($parentWorkflow->id());
        $storedParentWorkflow->status = WorkflowPendingStatus::class;
        $storedParentWorkflow->save();

        $storedParentWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => now(),
                'class' => TestChildWorkflow::class,
                'result' => Y::serialize('child_workflow'),
            ]);

        $storedParentWorkflow->logs()
            ->create([
                'index' => 1,
                'now' => now(),
                'class' => TestActivity::class,
                'result' => Y::serialize('activity'),
            ]);

        $childWorkflow = WorkflowStub::load(WorkflowStub::make(TestChildWorkflow::class)->id());

        $storedChildWorkflow = StoredWorkflow::findOrFail($childWorkflow->id());
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
                'result' => Y::serialize('other'),
            ]);

        (new (TestParentWorkflow::class)($storedParentWorkflow))->handle();

        $this->assertSame('2022-01-01 00:00:00', WorkflowStub::now()->toDateTimeString());
        $this->assertSame(WorkflowCompletedStatus::class, $parentWorkflow->status());
        $this->assertSame('workflow_activity_child_workflow', $parentWorkflow->output());
        $this->assertSame(2, $parentWorkflow->logs()->count());
    }
}
