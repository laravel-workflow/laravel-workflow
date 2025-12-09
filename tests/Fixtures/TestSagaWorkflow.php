<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use function Workflow\activity;
use Workflow\Workflow;

class TestSagaWorkflow extends Workflow
{
    public function execute($shouldThrow = false)
    {
        try {
            yield activity(TestActivity::class);
            $this->addCompensation(static fn () => activity(TestUndoActivity::class));

            yield activity(TestSagaActivity::class);
            $this->addCompensation(static fn () => activity(TestSagaUndoActivity::class));
        } catch (\Throwable $th) {
            yield from $this->compensate();
            if ($shouldThrow) {
                throw $th;
            }
        }

        return 'saga_workflow';
    }
}
