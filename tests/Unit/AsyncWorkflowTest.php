<?php

declare(strict_types=1);

namespace Tests\Unit;

use Laravel\SerializableClosure\SerializableClosure;
use Tests\TestCase;
use Workflow\AsyncWorkflow;
use Workflow\Models\StoredWorkflow;
use Workflow\WorkflowStub;

final class AsyncWorkflowTest extends TestCase
{
    public function testWorkflow(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(AsyncWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $workflow->start(new SerializableClosure(static fn () => 'test'));
        $this->assertSame('test', $workflow->output());
    }
}
