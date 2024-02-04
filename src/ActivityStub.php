<?php

declare(strict_types=1);

namespace Workflow;

use Closure;
use Generator;
use Laravel\SerializableClosure\Exceptions\PhpVersionNotSupportedException;
use Laravel\SerializableClosure\SerializableClosure;
use function React\Promise\all;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use Throwable;
use Workflow\Serializers\Y;

/**
 * @template TWorkflow of Workflow
 * @template TReturn
 * @template TActivity of Activity<TWorkflow, TReturn>
 */
final class ActivityStub
{
    /**
     * @param iterable<PromiseInterface<TActivity<TWorkflow, TReturn>>> $promises
     * @return PromiseInterface<TActivity<TWorkflow, TReturn>[]>
     */
    public static function all(iterable $promises): PromiseInterface
    {
        return all([...$promises]);
    }

    /**
     * @param callable $callback
     * @return PromiseInterface<mixed>
     * @throws PhpVersionNotSupportedException
     *
     * we can only be more specific with the callable when https://github.com/phpstan/phpstan/issues/8214
     * is implemented
     */
    public static function async(callable $callback): PromiseInterface
    {
        return ChildWorkflowStub::make(AsyncWorkflow::class, new SerializableClosure($callback));
    }

    /**
     * @template TMakeWorkflowClass of Workflow
     * @template TMakeActivityReturn
     * @template TMakeActivityClass of Activity<TMakeWorkflowClass, TMakeActivityReturn>
     * @param class-string<TMakeActivityClass> $activity
     * @param mixed ...$arguments
     * @return PromiseInterface<TMakeActivityClass>
     */
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
                        'result' => Y::serialize(is_callable($result) ? $result($context, ...$arguments) : $result),
                    ]);

                WorkflowStub::recordDispatched($activity, $arguments);
            }
        }

        if ($log) {
            ++$context->index;
            WorkflowStub::setContext($context);
            $result = Y::unserialize($log->result);
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
