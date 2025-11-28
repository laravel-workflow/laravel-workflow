<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\TestMultiStageApprovalWorkflow;
use Tests\TestCase;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\WorkflowStub;

final class MultiStageApprovalWorkflowTest extends TestCase
{
    public function testFullApprovalFlow(): void
    {
        $workflow = WorkflowStub::make(TestMultiStageApprovalWorkflow::class);

        $workflow->start();

        $workflow->approveManager(true);
        $workflow->approveFinance(true);
        $workflow->approveLegal(true);
        $workflow->approveExecutive(true);

        while ($workflow->running());

        $this->assertSame(WorkflowCompletedStatus::class, $workflow->status());
        $this->assertSame('approved', $workflow->output());
    }

    public function testApprovalFlowWithDelays(): void
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
