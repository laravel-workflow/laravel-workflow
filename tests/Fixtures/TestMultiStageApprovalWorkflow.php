<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\SignalMethod;
use Workflow\Workflow;
use function Workflow\{activity, all, await};

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
        yield activity(TestRequestManagerApprovalActivity::class);

        yield await(fn () => $this->managerApproved);

        yield all([
            activity(TestRequestFinanceApprovalActivity::class),
            activity(TestRequestLegalApprovalActivity::class),
        ]);

        yield await(fn () => $this->financeApproved && $this->legalApproved);

        yield activity(TestRequestExecutiveApprovalActivity::class);

        yield await(fn () => $this->executiveApproved);

        return 'approved';
    }
}
