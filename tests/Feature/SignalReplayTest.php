<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class SignalReplayTest extends TestCase
{
    /**
     * Case 1: Pure await workflow - no activities, just await signal
     */
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

    /**
     * Case 2: Activity then await - signal comes after activity completes
     */
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

    /**
     * Case 3: Multiple sequential awaits - signals sent with delays
     */
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

    /**
     * Case 4: Activity -> await -> activity -> await pattern
     */
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

    /**
     * Case 5: All signals sent before workflow even starts processing
     */
    public function testSignalsSentBeforeProcessing(): void
    {
        $workflow = WorkflowStub::make(TestMultipleAwaitsWorkflow::class);
        
        // Send signals immediately after start, before any processing
        $workflow->start();
        $workflow->approveFirst(true);
        $workflow->approveSecond(true);

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('both_approved', $workflow->output());
    }

    /**
     * Case 6: Activity (throws) -> await -> retry pattern (like SignalException test)
     */
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
}
