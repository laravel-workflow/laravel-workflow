<?php

declare(strict_types=1);

namespace Workflow;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\all;
use function React\Promise\resolve;

final class ActivityStub
{
    private $arguments;

    private function __construct(
        protected $activity,
        ...$arguments
    ) {
        $this->arguments = $arguments;
    }

    public static function all(iterable $promises): PromiseInterface
    {
        return all([...$promises]);
    }

    public static function make($activity, ...$arguments): PromiseInterface
    {
        $context = WorkflowStub::getContext();

        $log = $context->storedWorkflow->logs()
            ->whereIndex($context->index)
            ->first();

        if ($log) {
            ++$context->index;
            WorkflowStub::setContext($context);
            return resolve($log->result);
        } else {
            $current = new self($activity, ...$arguments);

            $current->activity()::dispatch(
                $context->index,
                $context->now,
                $context->storedWorkflow,
                ...$current->arguments()
            );

            ++$context->index;
            WorkflowStub::setContext($context);

            $deferred = new Deferred();

            return $deferred->promise();
        }
    }

    public function activity()
    {
        return $this->activity;
    }

    public function arguments()
    {
        return $this->arguments;
    }
}
