<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestVersionWorkflow extends Workflow
{
    public $connection = 'redis';

    public $queue = 'default';

    public function execute()
    {
        $version = yield WorkflowStub::getVersion('step-1', WorkflowStub::DEFAULT_VERSION, 2);

        $result = match ($version) {
            WorkflowStub::DEFAULT_VERSION => yield ActivityStub::make(TestVersionedActivityV1::class),
            1 => yield ActivityStub::make(TestVersionedActivityV2::class),
            2 => yield ActivityStub::make(TestVersionedActivityV3::class),
        };

        $version2 = yield WorkflowStub::getVersion('step-2', WorkflowStub::DEFAULT_VERSION, 1);

        $result2 = $version2 === WorkflowStub::DEFAULT_VERSION
            ? 'old_path'
            : 'new_path';

        return [
            'version1' => $version,
            'result1' => $result,
            'version2' => $version2,
            'result2' => $result2,
        ];
    }
}
