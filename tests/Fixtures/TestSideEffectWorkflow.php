<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Exception;
use Workflow\Workflow;
use function Workflow\{activity, sideEffect};

class TestSideEffectWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute()
    {
        $sideEffect = yield sideEffect(static fn () => random_int(PHP_INT_MIN, PHP_INT_MAX));

        $badSideEffect = random_int(PHP_INT_MIN, PHP_INT_MAX);

        $result = yield activity(TestActivity::class);

        $otherResult1 = yield activity(TestOtherActivity::class, $sideEffect);

        $otherResult2 = yield activity(TestOtherActivity::class, $badSideEffect);

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
