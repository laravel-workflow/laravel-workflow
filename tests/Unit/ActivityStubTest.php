<?php

declare(strict_types=1);

namespace Tests\Unit;

use Exception;
use Tests\Fixtures\TestActivity;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\ActivityStub;
use Workflow\Models\StoredWorkflow;
use Workflow\Models\StoredWorkflowLog;
use Workflow\Serializers\Y;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;
use function PHPStan\dumpType;

final class ActivityStubTest extends TestCase
{
    public function testStoresResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Y::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);

        ActivityStub::make(TestActivity::class)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertNull($result);
        $this->assertSame(WorkflowPendingStatus::class, $workflow->status());
        $this->assertNull($workflow->output());
        $this->assertSame(1, $workflow->logs()->count());
        $this->assertDatabaseHas('workflow_logs', [
            'stored_workflow_id' => $workflow->id(),
            'index' => 0,
            'class' => TestActivity::class,
        ]);
        $firstLog = $workflow->logs()->firstWhere('index', 0);
        $this->assertInstanceOf(StoredWorkflowLog::class, $firstLog);
        $this->assertNotNull($firstLog->result);

        $this->assertSame('activity', Y::unserialize($firstLog->result));
    }

    public function testLoadsStoredResult(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Y::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => TestActivity::class,
                'result' => Y::serialize('test'),
            ]);

        ActivityStub::make(TestActivity::class)
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertSame('test', $result);
    }

    public function testLoadsStoredException(): void
    {
        $this->expectException(Exception::class);

        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        /** @var StoredWorkflow<TestWorkflow, null> $storedWorkflow */
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Y::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => TestActivity::class,
                'result' => Y::serialize(new Exception('test')),
            ]);

        ActivityStub::make(TestActivity::class)
            ->then(static function ($value) use (&$result) {
                /**
                 * phpstan correctly infers the "string" type here as the result of this activity
                 * can only be a string. However, an exception is manually placed in the logs...
                 *
                 * this leads to an error below
                 */
                $result = $value;
            });

        /**
         * @phpstan-ignore-next-line
         */
        $this->assertSame('test', $result['message']);
    }

    public function testAll(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Y::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => TestActivity::class,
                'result' => Y::serialize('test'),
            ]);

        ActivityStub::all([ActivityStub::make(TestActivity::class)])
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertSame(['test'], $result);
    }

    public function testAsync(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $storedWorkflow->update([
            'arguments' => Y::serialize([]),
            'status' => WorkflowPendingStatus::$name,
        ]);
        $storedWorkflow->logs()
            ->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => TestActivity::class,
                'result' => Y::serialize('test'),
            ]);

        ActivityStub::async(static function () {
            yield ActivityStub::make(TestActivity::class);
        })
            ->then(static function ($value) use (&$result) {
                $result = $value;
            });

        $this->assertSame('test', $result);
    }
}
