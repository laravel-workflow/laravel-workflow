<?php

declare(strict_types=1);

namespace Workflow;

use Laravel\SerializableClosure\SerializableClosure;
use function React\Promise\all;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use Throwable;
use Workflow\Serializers\Serializer;

final class ActivityStub
{
    public static function all(iterable $promises): PromiseInterface
    {
        return all([...$promises]);
    }

    public static function async(callable $callback): PromiseInterface
    {
        return ChildWorkflowStub::make(AsyncWorkflow::class, new SerializableClosure($callback));
    }

    public static function make($activity, ...$arguments): PromiseInterface
    {
        $context = WorkflowStub::getContext();

        $log = $context->storedWorkflow->logs()
            ->whereIndex($context->index)
            ->first();

        if (WorkflowStub::faked()) {
            $mocks = WorkflowStub::mocks();

            if (! $log && array_key_exists($activity, $mocks)) {
                $result = $mocks[$activity];

                $log = $context->storedWorkflow->logs()
                    ->create([
                        'index' => $context->index,
                        'now' => $context->now,
                        'class' => $activity,
                        'result' => Serializer::serialize(
                            is_callable($result) ? $result($context, ...$arguments) : $result
                        ),
                    ]);

                WorkflowStub::recordDispatched($activity, $arguments);
            }
        }

        if ($log) {
            ++$context->index;
            WorkflowStub::setContext($context);
            $result = Serializer::unserialize($log->result);
            if (
                is_array($result) &&
                array_key_exists('class', $result) &&
                is_subclass_of($result['class'], Throwable::class)
            ) {
                throw new $result['class']($result['message'], (int) $result['code']);
            }
            return resolve($result);
        }

        $activity::dispatch($context->index, $context->now, $context->storedWorkflow, ...$arguments);

        ++$context->index;
        WorkflowStub::setContext($context);
        $deferred = new Deferred();
        return $deferred->promise();
    }
}
