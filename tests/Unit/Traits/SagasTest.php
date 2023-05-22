<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use Exception;
use Tests\Fixtures\TestWorkflow;
use Tests\TestCase;
use Workflow\Models\StoredWorkflow;
use Workflow\WorkflowStub;

final class SagasTest extends TestCase
{
    public function testCompensation(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $job = new ($storedWorkflow->class)($storedWorkflow, []);
        $job->addCompensation(static fn () => true);
        $this->assertSame([true], iterator_to_array($job->compensate()));
    }

    public function testCompensationWithError(): void
    {
        $this->expectException(Exception::class);
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $job = new ($storedWorkflow->class)($storedWorkflow, []);
        $job->addCompensation(static fn () => throw new Exception('error'));
        $this->assertSame([true], iterator_to_array($job->compensate()));
    }

    public function testCompensationContinueWithError(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $job = new ($storedWorkflow->class)($storedWorkflow, []);
        $job->addCompensation(static fn () => throw new Exception('error'));
        $job->setContinueWithError(true);
        $this->assertSame([], iterator_to_array($job->compensate()));
    }

    public function testParallelCompensation(): void
    {
        $workflow = WorkflowStub::load(WorkflowStub::make(TestWorkflow::class)->id());
        $storedWorkflow = StoredWorkflow::findOrFail($workflow->id());
        $job = new ($storedWorkflow->class)($storedWorkflow, []);
        $job->addCompensation(static fn () => true);
        $job->setParallelCompensation(true);
        iterator_to_array($job->compensate())[0]
            ->then(fn ($result) => $this->assertSame([true], $result));
    }
}
