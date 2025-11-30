<?php

declare(strict_types=1);

namespace Workflow;

use Workflow\Models\StoredWorkflow;

final class ChildWorkflowHandle
{
    public function __construct(
        public readonly StoredWorkflow $storedWorkflow
    ) {
    }

    public function __call($method, $arguments)
    {
        $context = WorkflowStub::getContext();

        if ($context->replaying) {
            return;
        }

        $savedContext = [
            'storedWorkflow' => $context->storedWorkflow,
            'index' => $context->index,
            'now' => $context->now,
            'replaying' => $context->replaying,
        ];

        $result = WorkflowStub::fromStoredWorkflow($this->storedWorkflow)->{$method}(...$arguments);

        WorkflowStub::setContext($savedContext);

        return $result;
    }

    public function id(): int
    {
        return $this->storedWorkflow->id;
    }
}
