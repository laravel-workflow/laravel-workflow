<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Workflow\Workflow;
use Workflow\WorkflowStub;
use function Workflow\{activity, child};

final class TestParentWorkflowWithContextCheck extends Workflow
{
    public function execute()
    {
        $childPromise = child(TestSimpleChildWorkflowWithSignal::class, 'context_check');

        $contextBefore = WorkflowStub::getContext();
        $indexBefore = $contextBefore->index;
        $nowBefore = $contextBefore->now;
        $storedWorkflowBefore = $contextBefore->storedWorkflow->id;

        $childHandle = $this->child();
        $childHandle->approve('approved');

        $contextAfter = WorkflowStub::getContext();
        $indexAfter = $contextAfter->index;
        $nowAfter = $contextAfter->now;
        $storedWorkflowAfter = $contextAfter->storedWorkflow->id;

        if ($indexBefore !== $indexAfter) {
            return 'context_corrupted:index:' . $indexBefore . ':' . $indexAfter;
        }

        if ($nowBefore->timestamp !== $nowAfter->timestamp) {
            return 'context_corrupted:now:' . $nowBefore->timestamp . ':' . $nowAfter->timestamp;
        }

        if ($storedWorkflowBefore !== $storedWorkflowAfter) {
            return 'context_corrupted:workflow:' . $storedWorkflowBefore . ':' . $storedWorkflowAfter;
        }

        yield activity(TestActivity::class);

        $result = yield $childPromise;

        return 'success';
    }
}
