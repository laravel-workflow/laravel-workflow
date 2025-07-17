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

/** ActivityStub - A class called from within a Workflow to execute an Activity
 *
 * This class is called from within the execute() method of a Workflow class to execute an Activity. It has three
 * method:
 * - async: accept a callable ane execute it as if it were a defined child workflow
 * - make: returns an unfilled promise if that activity has not been executed yet, otherwise returns a fulfilled
 * promise with the result of the activity from when it was executed.
 * - all: accepts an array of ActivityStub::make() promises and returns an array of unfilled/fulfilled promises. It is
 * similar to ActivityStub::make() but it allows the system to execute multiple activities at the same time.
 */
final class ActivityStub
{
    /**
     * This method accepts an array of ActivityStub::make() promises and returns an array of unfilled/fulfilled promises.
     * It is similar to ActivityStub::make() but it allows the system to execute multiple activities at the same time.
     */
    public static function all(iterable $promises): PromiseInterface
    {
        return all([...$promises]);
    }

    /**
     * This method accepts a callable and returns a promise that will execute that callable as if it were a defined
     * child workflow. It is used to execute a callback function directly from within a Workflow without having to
     * define a child workflow.
     */
    public static function async(callable $callback): PromiseInterface
    {
        return ChildWorkflowStub::make(AsyncWorkflow::class, new SerializableClosure($callback));
    }

    /**
     * This method accepts the class name for an activity that extends the Activity class and the arguments to pass
     * to that activity. When this is called from within a workflow for the first time, this will dispatch the
     * activity to the queue and return an unfilled promise. The workflow will then exit and wait for the activity
     * to complete. When this is called again (when the workflow is resumed), this will return a fulfilled promise
     * with the result of the activity and the workflow will continue with the result.
     */
    public static function make($activity, ...$arguments): PromiseInterface
    {
        $context = WorkflowStub::getContext();

        // Query the database to see if the activity has already been executed
        $log = $context->storedWorkflow->logs()
            ->whereIndex($context->index)
            ->first();

        // If we are running unit tests, and we have a mock for this activity, then
        // use the mock instead of dispatching the activity.
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

        // If the activity has already been executed and the result was available in the database, then
        // return a fulfilled promise with that result.
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

        // At this point, we know that the activity has not yet been executed. Dispatch it to the queue and
        // return an unfilled promise to signal to the workflow that it can exit and wait for the activity to
        // complete.
        $activity::dispatch($context->index, $context->now, $context->storedWorkflow, ...$arguments);

        ++$context->index;
        WorkflowStub::setContext($context);
        $deferred = new Deferred();
        return $deferred->promise();
    }
}
