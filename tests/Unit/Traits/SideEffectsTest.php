<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Exception;
use Illuminate\Support\Carbon;
use Tests\Fixtures\TestAwaitWorkflow;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Y;
use Workflow\Signal;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\States\WorkflowPendingStatus;
use Workflow\WorkflowStub;

final class SideEffectsTest extends TestCase
{
    public function testTrait(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());

        $promise = WorkflowStub::sideEffect(static fn () => 'test');

        $promise->then(fn($result) => $this->assertSame('test', $result));
        $this->assertSame(1, $workflow->logs()->count());
        $this->assertDatabaseHas('workflow_logs', [
            'stored_workflow_id' => $workflow->id(),
            'index' => 0,
            'class' => TestWorkflow::class,
            'result' => Y::serialize('test'),
        ]);

        $promise = WorkflowStub::sideEffect(static fn () => '');

        $promise->then(fn($result) => $this->assertSame('test', $result));
        $this->assertSame(1, $workflow->logs()->count());

        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());

        $promise = WorkflowStub::sideEffect(static function() use ($workflow) {
            $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
            $storedWorkflow->logs()->create([
                'index' => 0,
                'now' => WorkflowStub::now(),
                'class' => TestWorkflow::class,
                'result' => Y::serialize('test'),
            ]);
            return 'test';
        });

        $promise->then(fn($result) => $this->assertSame('test', $result));
        $this->assertSame(1, $workflow->logs()->count());
    }
}
