<?php

declare(strict_types=1);

namespace Workflow;

use function React\Promise\all;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use Workflow\Serializers\Serializer;

final class ChildWorkflowStub
{
    public static function all(iterable $promises): PromiseInterface
    {
        return all([...$promises]);
    }

    public static function make($workflow, ...$arguments): PromiseInterface
    {
        $context = WorkflowStub::getContext();

        $log = $context->storedWorkflow->logs()
            ->whereIndex($context->index)
            ->first();

        if (WorkflowStub::faked()) {
            $mocks = WorkflowStub::mocks();

            if (! $log && array_key_exists($workflow, $mocks)) {
                $result = $mocks[$workflow];

                $log = $context->storedWorkflow->logs()
                    ->create([
                        'index' => $context->index,
                        'now' => $context->now,
                        'class' => $workflow,
                        'result' => Serializer::serialize(
                            is_callable($result) ? $result($context, ...$arguments) : $result
                        ),
                    ]);

                WorkflowStub::recordDispatched($workflow, $arguments);
            }
        }

        if ($log) {
            ++$context->index;
            WorkflowStub::setContext($context);
            return resolve(Serializer::unserialize($log->result));
        }

        if (! $context->replaying) {
            $storedChildWorkflow = $context->storedWorkflow->children()
                ->wherePivot('parent_index', $context->index)
                ->first();

            $childWorkflow = $storedChildWorkflow ? $storedChildWorkflow->toWorkflow() : WorkflowStub::make($workflow);

            if ($childWorkflow->running() && ! $childWorkflow->created()) {
                try {
                    $childWorkflow->resume();
                } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
                    // already running
                }
            } elseif (! $childWorkflow->completed()) {
                $childWorkflow->startAsChild($context->storedWorkflow, $context->index, $context->now, ...$arguments);
            }
        }

        ++$context->index;
        WorkflowStub::setContext($context);
        $deferred = new Deferred();
        return $deferred->promise();
    }
}
