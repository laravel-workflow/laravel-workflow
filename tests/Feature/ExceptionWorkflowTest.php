<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Workflow\WorkflowStub;
use Workflow\Serializers\Y;
use Tests\Fixtures\TestExceptionWorkflow;
use Workflow\States\WorkflowFailedStatus;
use Workflow\States\WorkflowCompletedStatus;
use Tests\Fixtures\NonRetryableTestExceptionActivity;

final class ExceptionWorkflowTest extends TestCase
{
    public function testRetry(): void
    {
        $workflow = WorkflowStub::make(TestExceptionWorkflow::class);

        $workflow->start();

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('workflow_activity_other', $workflow->output());
        if ($workflow->exceptions()->first()) {
            $this->assertSame('failed', Y::unserialize($workflow->exceptions()->first()->exception)['message']);
        }
    }

    public function testNonRetryableException(): void
    {
        $workflow = WorkflowStub::make(NonRetryableTestExceptionActivity::class);

        $workflow->start();

        while ($workflow->running());

        $this->assertSame(WorkflowFailedStatus::class, $workflow->status());
        $this->assertNotNull($workflow->exceptions()->first());
        $this->assertNull($workflow->output());
        $this->assertSame('This is a non-retryable error', Y::unserialize($workflow->exceptions()->first()->exception)['message']);

        $this->assertCount(1, $workflow->exceptions());
    }
}
