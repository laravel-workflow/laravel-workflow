<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Tests\Fixtures\TestActivity;
use Tests\Fixtures\TestChildWorkflow;
use Tests\Fixtures\TestOtherActivity;
use Tests\Fixtures\TestParentWorkflow;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Exception;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Y;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;

final class WorkflowTest extends TestCase
{
    public function testException(): void
    {
        $exception = new \Exception('test');
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $activity = new Exception(0, now()->toDateTimeString(), StoredWorkflow::whereUuid(
            $workflow->id()
        )->firstOrFail(), $exception);

        $result = $activity->handle();

        $this->assertSame($exception, $result);
    }

    public function testExceptionAlreadyLogged(): void
    {
        $exception = new \Exception('test');
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::whereUuid($workflow->id())->firstOrFail();
        $activity = new Exception(0, now()->toDateTimeString(), StoredWorkflow::whereUuid(
            $workflow->id()
        )->firstOrFail(), $exception);

        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => TestOtherActivity::class,
                'result' => Y::serialize($exception),
            ]);

        $result = $activity->handle();

        $this->assertNull($result);
    }

    public function testParent(): void
    {
        Carbon::setTestNow('2022-01-01');

        $parentWorkflow = WorkflowStub::load(WorkflowStub::make(TestParentWorkflow::class)->id());

        $storedParentWorkflow = StoredWorkflow::whereUuid($parentWorkflow->id())->firstOrFail();
        $storedParentWorkflow->arguments = Y::serialize([]);
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

        $storedChildWorkflow = StoredWorkflow::whereUuid($childWorkflow->id())->firstOrFail();
        $storedChildWorkflow->arguments = Y::serialize([]);
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

        (new (TestChildWorkflow::class)($storedChildWorkflow))->handle();
        (new (TestParentWorkflow::class)($storedParentWorkflow))->handle();

        $this->assertSame('2022-01-01 00:00:00', WorkflowStub::now()->toDateTimeString());
        $this->assertSame(WorkflowCompletedStatus::class, $childWorkflow->status());
        $this->assertSame('other', $childWorkflow->output());
    }
}
