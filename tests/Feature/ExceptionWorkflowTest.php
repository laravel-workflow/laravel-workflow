<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestExceptionWorkflow;
use Tests\TestCase;
use Workflow\Serializers\Y;
use Workflow\States\WorkflowCompletedStatus;
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
}
