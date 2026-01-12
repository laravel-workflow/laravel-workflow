<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Mockery;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Exceptions\VersionNotSupportedException;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;
use Workflow\WorkflowStub;

final class VersionsTest extends TestCase
{
    public function testStoresResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());

        WorkflowStub::getVersion('test-change', WorkflowStub::DEFAULT_VERSION, 1)
            ->then(static function ($value) use (&$result): void {
                $result = $value;
            });

        $this->assertSame(1, $result);
        $this->assertSame(1, $workflow->logs()->count());
        $this->assertDatabaseHas('workflow_logs', [
            'stored_workflow_id' => $workflow->id(),
            'index' => 0,
            'class' => 'version:test-change',
        ]);
        $this->assertSame(1, Serializer::unserialize($workflow->logs()->firstWhere('index', 0)->result));
    }

    public function testLoadsStoredResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => 'version:test-change',
                'result' => Serializer::serialize(WorkflowStub::DEFAULT_VERSION),
            ]);

        WorkflowStub::getVersion('test-change', WorkflowStub::DEFAULT_VERSION, 1)
            ->then(static function ($value) use (&$result): void {
                $result = $value;
            });

        $this->assertSame(WorkflowStub::DEFAULT_VERSION, $result);
        $this->assertSame(1, $workflow->logs()->count());
    }

    public function testThrowsExceptionWhenVersionBelowMinSupported(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => 'version:test-change',
                'result' => Serializer::serialize(WorkflowStub::DEFAULT_VERSION),
            ]);

        $this->expectException(VersionNotSupportedException::class);
        $this->expectExceptionMessage(
            "Version -1 for change ID 'test-change' is not supported. Supported range: [1, 2]"
        );

        WorkflowStub::getVersion('test-change', 1, 2);
    }

    public function testThrowsExceptionWhenVersionAboveMaxSupported(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => 'version:test-change',
                'result' => Serializer::serialize(99),
            ]);

        $this->expectException(VersionNotSupportedException::class);
        $this->expectExceptionMessage(
            "Version 99 for change ID 'test-change' is not supported. Supported range: [-1, 2]"
        );

        WorkflowStub::getVersion('test-change', WorkflowStub::DEFAULT_VERSION, 2);
    }

    public function testResolvesConflictingResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());

        WorkflowStub::getVersion('test-change', WorkflowStub::DEFAULT_VERSION, 1);

        WorkflowStub::setContext([
            'storedWorkflow' => StoredWorkflow::findOrFail($workflow->id()),
            'index' => 0,
            'now' => now(),
            'replaying' => false,
        ]);

        WorkflowStub::getVersion('test-change', WorkflowStub::DEFAULT_VERSION, 1)
            ->then(static function ($value) use (&$result): void {
                $result = $value;
            });

        $this->assertSame(1, $result);
        $this->assertSame(1, $workflow->logs()->count());
    }

    public function testResolvesConflictingResultWithValidVersion(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());

        $mockLogs = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class)
            ->shouldReceive('whereIndex')
            ->andReturnSelf()
            ->shouldReceive('first')
            ->once()
            ->andReturn(null)
            ->shouldReceive('create')
            ->andThrow(new \Illuminate\Database\QueryException('', '', [], new \Exception('Duplicate entry')))
            ->shouldReceive('first')
            ->once()
            ->andReturn((object) [
                'result' => Serializer::serialize(1),
            ])
            ->getMock();

        $mockStoredWorkflow = Mockery::spy($storedWorkflow);

        $mockStoredWorkflow->shouldReceive('logs')
            ->andReturnUsing(static function () use ($mockLogs) {
                return $mockLogs;
            });

        $mockStoredWorkflow->class = TestWorkflow::class;

        WorkflowStub::setContext([
            'storedWorkflow' => $mockStoredWorkflow,
            'index' => 0,
            'now' => now(),
            'replaying' => false,
        ]);

        WorkflowStub::getVersion('test-change', WorkflowStub::DEFAULT_VERSION, 2)
            ->then(static function ($value) use (&$result): void {
                $result = $value;
            });

        $this->assertSame(1, $result);

        Mockery::close();
    }

    public function testResolvesConflictingResultThrowsWhenVersionNotSupported(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());

        $mockLogs = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class)
            ->shouldReceive('whereIndex')
            ->andReturnSelf()
            ->shouldReceive('first')
            ->once()
            ->andReturn(null)
            ->shouldReceive('create')
            ->andThrow(new \Illuminate\Database\QueryException('', '', [], new \Exception('Duplicate entry')))
            ->shouldReceive('first')
            ->once()
            ->andReturn((object) [
                'result' => Serializer::serialize(99),
            ])
            ->getMock();

        $mockStoredWorkflow = Mockery::spy($storedWorkflow);

        $mockStoredWorkflow->shouldReceive('logs')
            ->andReturnUsing(static function () use ($mockLogs) {
                return $mockLogs;
            });

        $mockStoredWorkflow->class = TestWorkflow::class;

        WorkflowStub::setContext([
            'storedWorkflow' => $mockStoredWorkflow,
            'index' => 0,
            'now' => now(),
            'replaying' => false,
        ]);

        $this->expectException(VersionNotSupportedException::class);
        $this->expectExceptionMessage(
            "Version 99 for change ID 'test-change' is not supported. Supported range: [-1, 2]"
        );

        WorkflowStub::getVersion('test-change', WorkflowStub::DEFAULT_VERSION, 2);

        Mockery::close();
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

        $mockStoredWorkflow->class = TestWorkflow::class;

        WorkflowStub::setContext([
            'storedWorkflow' => $mockStoredWorkflow,
            'index' => 0,
            'now' => now(),
            'replaying' => false,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        WorkflowStub::getVersion('test-change', WorkflowStub::DEFAULT_VERSION, 1);

        Mockery::close();
    }
}
