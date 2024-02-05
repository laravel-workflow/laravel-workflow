<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\Models\StoredWorkflowLog;
use Workflow\Serializers\Y;
use Workflow\Signal;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;

final class AwaitWithTimeoutsTest extends TestCase
{
    public function testDefersIfNoResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Y::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);

        WorkflowStub::awaitWithTimeout(60, static fn () => false)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertNull($result);
        $this->assertSame(0, $workflow->logs()->count());

        WorkflowStub::awaitWithTimeout('1 minute', static fn () => false)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertNull($result);
        $this->assertSame(0, $workflow->logs()->count());
    }

    public function testStoresResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());

        WorkflowStub::awaitWithTimeout('1 minute', static fn () => true)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertSame(true, $result);
        $this->assertSame(1, $workflow->logs()->count());
        $this->assertDatabaseHas('workflow_logs', [
            'stored_workflow_id' => $workflow->id(),
            'index' => 0,
            'class' => Signal::class,
        ]);
        $firstLog = $workflow->logs()
            ->firstWhere('index', 0);
        $this->assertInstanceOf(StoredWorkflowLog::class, $firstLog);
        $this->assertNotNull($firstLog->result);
        $this->assertTrue(Y::unserialize($firstLog->result));
    }

    public function testLoadsStoredResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => Signal::class,
                'result' => Y::serialize(true),
            ]);

        WorkflowStub::awaitWithTimeout('1 minute', static fn () => true)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertSame(true, $result);
        $this->assertSame(1, $workflow->logs()->count());
        $this->assertDatabaseHas('workflow_logs', [
            'stored_workflow_id' => $workflow->id(),
            'index' => 0,
            'class' => Signal::class,
        ]);
        $firstLog = $workflow->logs()
            ->firstWhere('index', 0);
        $this->assertInstanceOf(StoredWorkflowLog::class, $firstLog);
        $this->assertNotNull($firstLog->result);
        $this->assertTrue(Y::unserialize($firstLog->result));
    }

    public function testResolvesConflictingResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());

        WorkflowStub::awaitWithTimeout('1 minute', static function () use ($workflow) {
            $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
            $storedWorkflow->logs()
                ->create([
                    'index' => 0,
                    'now' => WorkflowStub::now(),
                    'class' => Signal::class,
                    'result' => Y::serialize(false),
                ]);
            return true;
        })
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertSame(false, $result);
        $this->assertSame(1, $workflow->logs()->count());
        $this->assertDatabaseHas('workflow_logs', [
            'stored_workflow_id' => $workflow->id(),
            'index' => 0,
            'class' => Signal::class,
        ]);
        $firstLog = $workflow->logs()
            ->firstWhere('index', 0);
        $this->assertInstanceOf(StoredWorkflowLog::class, $firstLog);
        $this->assertNotNull($firstLog->result);
        $this->assertFalse(Y::unserialize($firstLog->result));
    }
}
