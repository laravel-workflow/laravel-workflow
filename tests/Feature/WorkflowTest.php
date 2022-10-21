<?php

namespace Tests\Feature;

use Tests\TestCase;

use Tests\TestWorkflow;
use Workflow\Exceptions\WorkflowFailedException;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\WorkflowStub;

class WorkflowTest extends TestCase
{
    public function testCompleted()
    {
        $workflow = WorkflowStub::make(TestWorkflow::class);

        $workflow->start();

        $workflow->cancel();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
    }

    public function testCompletedDelay()
    {
        $workflow = WorkflowStub::make(TestWorkflow::class);

        $workflow->start(false, true);

        sleep(5);

        $workflow->cancel();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
    }

    public function testFailed()
    {
        $workflow = WorkflowStub::make(TestWorkflow::class);

        $workflow->start(true);

        while ($workflow->running());

        $this->assertSame(WorkflowFailedStatus::class, $workflow->status());
        $this->assertStringContainsString('TestFailingActivity', $workflow->output());
    }

    public function testRecoveredFailed()
    {
        $workflow = WorkflowStub::make(TestWorkflow::class);

        $workflow->start(true);

        while ($workflow->running());

        $this->assertSame(WorkflowFailedStatus::class, $workflow->status());
        $this->assertStringContainsString('TestFailingActivity', $workflow->output());

        $workflow->fresh()->start();

        $workflow->cancel();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
    }
}
