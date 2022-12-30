<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Y;
use Workflow\Signal;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;

final class TimersTest extends TestCase
{
    public function testResolvesZeroSeconds(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());

        WorkflowStub::timer(0)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertTrue($result);
        $this->assertSame(0, $workflow->logs()->count());
    }

    public function testCreatesTimer(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Y::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);

        WorkflowStub::timer('1 minute')
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertNull($result);
        $this->assertSame(0, $workflow->logs()->count());
        $this->assertDatabaseHas('workflow_timers', [
            'stored_workflow_id' => $workflow->id(),
            'index' => 0,
            'stop_at' => WorkflowStub::now()->addMinute(),
        ]);
    }

    public function testDefersIfNotElapsed(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Y::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);
        $storedWorkflow->timers()
            ->create([
                'index' => 0,
                'stop_at' => now()
                    ->addHour(),
            ]);

        WorkflowStub::timer(60)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertNull($result);
        $this->assertSame(0, $workflow->logs()->count());

        WorkflowStub::timer('1 minute', static fn () => false)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertNull($result);
        $this->assertSame(0, $workflow->logs()->count());
    }

    public function testStoresResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->timers()
            ->create([
                'index' => 0,
                'stop_at' => now(),
            ]);

        WorkflowStub::timer('1 minute')
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
        $this->assertSame(true, Y::unserialize($workflow->logs()->firstWhere('index', 0)->result));
    }

    public function testLoadsStoredResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->timers()
            ->create([
                'index' => 0,
                'stop_at' => now(),
            ]);
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => now(),
                'class' => Signal::class,
                'result' => Y::serialize(true),
            ]);

        WorkflowStub::timer('1 minute', static fn () => true)
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
        $this->assertSame(true, Y::unserialize($workflow->logs()->firstWhere('index', 0)->result));
    }
}
