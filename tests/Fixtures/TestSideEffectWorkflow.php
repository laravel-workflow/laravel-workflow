<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestSideEffectWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute()
    {
        $sideEffect = yield WorkflowStub::sideEffect(static fn () => random_int(PHP_INT_MIN, PHP_INT_MAX));

        $badSideEffect = random_int(PHP_INT_MIN, PHP_INT_MAX);

        $result = yield ActivityStub::make(TestActivity::class);

        $otherResult1 = yield ActivityStub::make(TestOtherActivity::class, $sideEffect);

        $otherResult2 = yield ActivityStub::make(TestOtherActivity::class, $badSideEffect);

        if ($sideEffect !== $otherResult1) {
            throw new Exception(
                'These side effects should match because it was properly wrapped in WorkflowStub::sideEffect().'
            );
        }

        if ($badSideEffect === $otherResult2) {
            throw new Exception(
                'These side effects should not match because it was not wrapped in WorkflowStub::sideEffect().'
            );
        }

        return 'workflow';
    }
}
