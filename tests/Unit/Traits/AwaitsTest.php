<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Mockery;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;
use Workflow\Signal;
use Workflow\WorkflowStub;

final class AwaitsTest extends TestCase
{
    public function testDefersIfNoResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());

        WorkflowStub::await(static fn () => false)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertNull($result);
        $this->assertSame(0, $workflow->logs()->count());
    }

    public function testStoresResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());

        WorkflowStub::await(static fn () => true)
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
        $this->assertTrue(Serializer::unserialize($workflow->logs()->firstWhere('index', 0)->result));
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
                'result' => Serializer::serialize(true),
            ]);

        WorkflowStub::await(static fn () => true)
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
        $this->assertTrue(Serializer::unserialize($workflow->logs()->firstWhere('index', 0)->result));
    }

    public function testResolvesConflictingResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());

        WorkflowStub::await(static function () use ($workflow) {
            $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
            $storedWorkflow->logs()
                ->create([
                    'index' => 0,
                    'now' => WorkflowStub::now(),
                    'class' => Signal::class,
                    'result' => Serializer::serialize(false),
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
        $this->assertFalse(Serializer::unserialize($workflow->logs()->firstWhere('index', 0)->result));
    }

    public function testThrowsQueryExceptionWhenNotDuplicateKey(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());

        $mockLogs = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class)
            ->shouldReceive('whereIndex')
            ->twice()
            ->andReturnSelf()
            ->shouldReceive('first')
            ->twice()
            ->andReturn(null)
            ->shouldReceive('create')
            ->andThrow(new \Illuminate\Database\QueryException('', '', [], new \Exception('Some other error')))
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

        $this->expectException(\Illuminate\Database\QueryException::class);

        WorkflowStub::await(static fn () => true);

        Mockery::close();
    }
}
