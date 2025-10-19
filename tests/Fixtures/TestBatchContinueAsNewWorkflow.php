<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class TestBatchContinueAsNewWorkflow extends Workflow
{
    public function execute(int $maxResults = 20, array $results = [])
    {
        while (count($results) < $maxResults) {
            $activities = [
                ActivityStub::make(ProcessRecordActivity::class),
                ActivityStub::make(ProcessRecordActivity::class),
                ActivityStub::make(ProcessRecordActivity::class),
                ActivityStub::make(ProcessRecordActivity::class),
            ];

            $results = array_merge($results, yield ActivityStub::all($activities));

            return yield WorkflowStub::continueAsNew($maxResults, $results);
        }

        return $results;
    }
}
