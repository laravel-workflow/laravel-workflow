<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\SignalMethod;
use Workflow\Workflow;
use function Workflow\{activity, await};

final class TestActivityThenAwaitWorkflow extends Workflow
{
    public bool $approved = false;

    #[SignalMethod]
    public function approve(bool $value): void
    {
        $this->approved = $value;
    }

    public function execute()
    {
        yield activity(TestActivity::class);

        yield await(fn () => $this->approved);

        return 'approved';
    }
}
