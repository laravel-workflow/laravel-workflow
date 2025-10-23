<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Carbon\CarbonInterval;
use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Mockery;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;
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
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);

        WorkflowStub::timer('1 minute')
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertNull($result);
        $this->assertSame(0, $workflow->logs()->count());

        // Verify timer was created with correct stop_at time
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $timer = $storedWorkflow->timers()
            ->first();
        $this->assertNotNull($timer);
        $this->assertSame(0, $timer->index);
        $this->assertTrue(
            $timer->stop_at->equalTo(WorkflowStub::now()->addMinute()),
            'Timer stop_at should match expected time'
        );
    }

    public function testDefersIfNotElapsed(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
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
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->timers()
            ->create([
                'index' => 0,
                'stop_at' => now()
                    ->subSecond(),  // Set to past to ensure it's elapsed
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
        $this->assertSame(true, Serializer::unserialize($workflow->logs()->firstWhere('index', 0)->result));
    }

    public function testLoadsStoredResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->timers()
            ->create([
                'index' => 0,
                'stop_at' => now()
                    ->subSecond(),
            ]);
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => now(),
                'class' => Signal::class,
                'result' => Serializer::serialize(true),
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
        $this->assertSame(true, Serializer::unserialize($workflow->logs()->firstWhere('index', 0)->result));
    }

    public function testHandlesDuplicateLogInsertionProperly(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->timers()
            ->create([
                'index' => 0,
                'stop_at' => now()
                    ->subSecond(),
            ]);
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => now(),
                'class' => Signal::class,
                'result' => Serializer::serialize(true),
            ]);

        $mockLogs = Mockery::mock(HasMany::class)
            ->shouldReceive('whereIndex')
            ->once()
            ->andReturnSelf()
            ->shouldReceive('first')
            ->once()
            ->andReturn(null)
            ->shouldReceive('create')
            ->andThrow(new UniqueConstraintViolationException('', '', [], new Exception()))
            ->getMock();

        $mockStoredWorkflow = Mockery::spy($storedWorkflow);

        $mockStoredWorkflow->shouldReceive('logs')
            ->andReturnUsing(static function () use ($mockLogs) {
                return $mockLogs;
            });

        WorkflowStub::setContext([
            'storedWorkflow' => $mockStoredWorkflow,
            'index' => 0,
            'now' => now(),
            'replaying' => false,
        ]);

        WorkflowStub::timer('1 minute')
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        Mockery::close();

        $this->assertSame(true, $result);
    }

    public function testTimerWithCarbonInterval(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Serializer::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);

        $interval = CarbonInterval::minutes(3);

        WorkflowStub::timer($interval)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertNull($result);
        $this->assertSame(0, $workflow->logs()->count());

        // Verify timer was created with correct stop_at time
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $timer = $storedWorkflow->timers()
            ->first();
        $this->assertNotNull($timer);
        $this->assertSame(0, $timer->index);
        $this->assertTrue(
            $timer->stop_at->equalTo(WorkflowStub::now()->addSeconds($interval->totalSeconds)),
            'Timer stop_at should match expected time'
        );
    }
}
