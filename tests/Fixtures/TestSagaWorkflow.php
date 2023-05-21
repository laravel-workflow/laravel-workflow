<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;

class TestSagaWorkflow extends Workflow
{
    public function execute($shouldThrow = false)
    {
        try {
            yield ActivityStub::make(TestActivity::class);
            $this->addCompensation(static fn () => ActivityStub::make(TestUndoActivity::class));

            yield ActivityStub::make(TestSagaActivity::class);
            $this->addCompensation(static fn () => ActivityStub::make(TestSagaUndoActivity::class));
        } catch (\Throwable $th) {
            yield from $this->compensate();
            if ($shouldThrow) {
                throw $th;
            }
        }

        return 'saga_workflow';
    }
}
