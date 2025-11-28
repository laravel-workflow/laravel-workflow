<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

final class TestMultiStageApprovalWorkflow extends Workflow
{
    public bool $managerApproved = false;

    public bool $financeApproved = false;

    public bool $legalApproved = false;

    public bool $executiveApproved = false;

    #[SignalMethod]
    public function approveManager(bool $value): void
    {
        $this->managerApproved = $value;
    }

    #[SignalMethod]
    public function approveFinance(bool $value): void
    {
        $this->financeApproved = $value;
    }

    #[SignalMethod]
    public function approveLegal(bool $value): void
    {
        $this->legalApproved = $value;
    }

    #[SignalMethod]
    public function approveExecutive(bool $value): void
    {
        $this->executiveApproved = $value;
    }

    public function execute()
    {
        yield ActivityStub::make(TestRequestManagerApprovalActivity::class);

        yield WorkflowStub::await(fn () => $this->managerApproved);

        yield ActivityStub::all([
            ActivityStub::make(TestRequestFinanceApprovalActivity::class),
            ActivityStub::make(TestRequestLegalApprovalActivity::class),
        ]);

        yield WorkflowStub::await(fn () => $this->financeApproved && $this->legalApproved);

        yield ActivityStub::make(TestRequestExecutiveApprovalActivity::class);

        yield WorkflowStub::await(fn () => $this->executiveApproved);

        return 'approved';
    }
}
