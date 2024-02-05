<?php

declare(strict_types=1);

namespace Workflow;

use function React\Promise\all;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use Workflow\Serializers\Y;

/**
 * @template TWorkflow of Workflow
 */
final class ChildWorkflowStub
{
    /**
     * @param iterable<mixed> $promises
     * @return PromiseInterface<mixed>
     */
    public static function all(iterable $promises): PromiseInterface
    {
        return all([...$promises]);
    }

    /**
     * @param class-string<TWorkflow> $workflow
     * @param mixed ...$arguments
     * @return PromiseInterface<void>
     */
    public static function make($workflow, ...$arguments): PromiseInterface
    {
        $context = WorkflowStub::getContext();
        if ($context === null) {
            throw new \RuntimeException('ActivityStub::make() must be called within a workflow');
        }

        $log = $context->storedWorkflow->logs()
            ->whereIndex($context->index)
            ->first();

        if (WorkflowStub::faked()) {
            $mocks = WorkflowStub::mocks();

            if ($log === null && array_key_exists($workflow, $mocks)) {
                $result = $mocks[$workflow];

                $log = $context->storedWorkflow->logs()
                    ->create([
                        'index' => $context->index,
                        'now' => $context->now,
                        'class' => $workflow,
                        'result' => Y::serialize(is_callable($result) ? $result($context, ...$arguments) : $result),
                    ]);

                WorkflowStub::recordDispatched($workflow, $arguments);
            }
        }

        if ($log !== null) {
            ++$context->index;
            WorkflowStub::setContext($context);
            return resolve($log->result !== null ? Y::unserialize($log->result) : null);
        }

        if (! $context->replaying) {
            $storedChildWorkflow = $context->storedWorkflow->children()
                ->wherePivot('parent_index', $context->index)
                ->first();

            $childWorkflow = $storedChildWorkflow !== null ? $storedChildWorkflow->toWorkflow() : WorkflowStub::make(
                $workflow
            );

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
