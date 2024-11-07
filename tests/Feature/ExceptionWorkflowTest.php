<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\NonRetryableTestExceptionActivity;
use Tests\Fixtures\NonRetryableTestExceptionWorkflow;
use Tests\Fixtures\TestExceptionWorkflow;
use Tests\TestCase;
use Workflow\Serializers\Y;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\WorkflowStub;

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
        $workflow = WorkflowStub::make(NonRetryableTestExceptionWorkflow::class);

        $workflow->start();

        while ($workflow->running());

        $this->assertSame(WorkflowFailedStatus::class, $workflow->status());
        $this->assertNotNull($workflow->exceptions()->first());
        $this->assertNull($workflow->output());
        $this->assertSame(
            'This is a non-retryable error',
            Y::unserialize($workflow->exceptions()->first()->exception)['message']
        );

        $this->assertCount(1, $workflow->exceptions());
    }
}
