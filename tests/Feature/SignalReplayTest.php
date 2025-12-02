<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestActivityAwaitActivityAwaitWorkflow;
use Tests\Fixtures\TestActivityThenAwaitWorkflow;
use Tests\Fixtures\TestActivityThrowsAwaitRetryWorkflow;
use Tests\Fixtures\TestMultipleAwaitsWorkflow;
use Tests\Fixtures\TestMultiStageApprovalWorkflow;
use Tests\Fixtures\TestPureAwaitWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class SignalReplayTest extends TestCase
{
    public function testPureAwait(): void
    {
        $workflow = WorkflowStub::make(TestPureAwaitWorkflow::class);
        $workflow->start();

        sleep(1);
        $workflow->approve(true);

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('approved', $workflow->output());
    }

    public function testActivityThenAwait(): void
    {
        $workflow = WorkflowStub::make(TestActivityThenAwaitWorkflow::class);
        $workflow->start();

        sleep(1);
        $workflow->approve(true);

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('approved', $workflow->output());
    }

    public function testMultipleAwaitsWithDelays(): void
    {
        $workflow = WorkflowStub::make(TestMultipleAwaitsWorkflow::class);
        $workflow->start();

        sleep(1);
        $workflow->approveFirst(true);
        sleep(1);
        $workflow->approveSecond(true);

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('both_approved', $workflow->output());
    }

    public function testActivityAwaitActivityAwait(): void
    {
        $workflow = WorkflowStub::make(TestActivityAwaitActivityAwaitWorkflow::class);
        $workflow->start();

        sleep(1);
        $workflow->approveFirst(true);
        sleep(1);
        $workflow->approveSecond(true);

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('completed', $workflow->output());
    }

    public function testSignalsSentBeforeProcessing(): void
    {
        $workflow = WorkflowStub::make(TestMultipleAwaitsWorkflow::class);

        $workflow->start();
        $workflow->approveFirst(true);
        $workflow->approveSecond(true);

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('both_approved', $workflow->output());
    }

    public function testActivityThrowsAwaitRetry(): void
    {
        $workflow = WorkflowStub::make(TestActivityThrowsAwaitRetryWorkflow::class);
        $workflow->start();

        sleep(3);
        $workflow->shouldRetry();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertTrue($workflow->output());
    }

    public function testMultiStageApprovalPattern(): void
    {
        $workflow = WorkflowStub::make(TestMultiStageApprovalWorkflow::class);
        $workflow->start();

        sleep(1);
        $workflow->approveManager(true);
        sleep(1);
        $workflow->approveFinance(true);
        sleep(1);
        $workflow->approveLegal(true);
        sleep(1);
        $workflow->approveExecutive(true);

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('approved', $workflow->output());
    }
}
