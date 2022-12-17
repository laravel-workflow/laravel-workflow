<?php

declare(strict_types=1);

namespace Workflow;

use Carbon\CarbonInterval;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use ReflectionClass;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Y;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\States\WorkflowPendingStatus;

final class WorkflowStub
{
    private static ?\stdClass $context = null;

    private function __construct(
        protected $storedWorkflow
    ) {
        self::setContext([
            'storedWorkflow' => $storedWorkflow,
            'index' => 0,
            'now' => Carbon::now(),
            'replaying' => false,
        ]);
    }

    public function __call($method, $arguments)
    {
        if (collect((new ReflectionClass($this->storedWorkflow->class))->getMethods())
            ->filter(static fn ($method): bool => collect($method->getAttributes())
                ->contains(static fn ($attribute): bool => $attribute->getName() === SignalMethod::class))
            ->map(static fn ($method) => $method->getName())
            ->contains($method)
        ) {
            $this->storedWorkflow->signals()
                ->create([
                    'method' => $method,
                    'arguments' => Y::serialize($arguments),
                ]);

            return Signal::dispatch($this->storedWorkflow);
        }

        if (collect((new ReflectionClass($this->storedWorkflow->class))->getMethods())
            ->filter(static fn ($method): bool => collect($method->getAttributes())
                ->contains(static fn ($attribute): bool => $attribute->getName() === QueryMethod::class))
            ->map(static fn ($method) => $method->getName())
            ->contains($method)
        ) {
            return (new $this->storedWorkflow->class(
                $this->storedWorkflow,
                ...Y::unserialize($this->storedWorkflow->arguments),
            ))
                ->query($method);
        }
    }

    public static function make($class): static
    {
        $storedWorkflow = config('workflows.stored_workflow_model', StoredWorkflow::class)::create([
            'class' => $class,
        ]);

        return new self($storedWorkflow);
    }

    public static function load($id)
    {
        return static::fromStoredWorkflow(
            config('workflows.stored_workflow_model', StoredWorkflow::class)::findOrFail($id)
        );
    }

    public static function fromStoredWorkflow(StoredWorkflow $storedWorkflow): static
    {
        return new self($storedWorkflow);
    }

    public static function getContext(): \stdClass
    {
        return self::$context;
    }

    public static function setContext($context): void
    {
        self::$context = (object) $context;
    }

    public static function await($condition): PromiseInterface
    {
        $result = $condition();

        if ($result === true) {
            if (! self::$context->replaying) {
                try {
                    self::$context->storedWorkflow->logs()
                        ->create([
                            'index' => self::$context->index,
                            'now' => self::$context->now,
                            'class' => Signal::class,
                            'result' => Y::serialize($result),
                        ]);
                } catch (QueryException $exception) {
                    ++self::$context->index;
                    $deferred = new Deferred();
                    return $deferred->promise();
                }
            }
            ++self::$context->index;
            return resolve(true);
        }

        ++self::$context->index;
        $deferred = new Deferred();
        return $deferred->promise();
    }

    public static function awaitWithTimeout($seconds, $condition): PromiseInterface
    {
        if (is_string($seconds)) {
            $seconds = CarbonInterval::fromString($seconds)->totalSeconds;
        }

        $result = $condition();

        if ($result === true) {
            if (! self::$context->replaying) {
                try {
                    self::$context->storedWorkflow->logs()
                        ->create([
                            'index' => self::$context->index,
                            'now' => self::$context->now,
                            'class' => Signal::class,
                            'result' => Y::serialize($result),
                        ]);
                } catch (QueryException $exception) {
                    ++self::$context->index;
                    $deferred = new Deferred();
                    return $deferred->promise();
                }
            }
            ++self::$context->index;
            return resolve($result);
        }

        ++self::$context->index;
        return self::timer($seconds)->then(static fn ($completed): bool => ! $completed);
    }

    public static function timer($seconds): PromiseInterface
    {
        if (is_string($seconds)) {
            $seconds = CarbonInterval::fromString($seconds)->totalSeconds;
        }

        if ($seconds <= 0) {
            ++self::$context->index;
            return resolve(true);
        }

        $timer = self::$context->storedWorkflow->timers()
            ->whereIndex(self::$context->index)
            ->first();

        if ($timer === null) {
            $when = self::$context->now->copy()
                ->addSeconds($seconds);

            if (! self::$context->replaying) {
                $timer = self::$context->storedWorkflow->timers()
                    ->create([
                        'index' => self::$context->index,
                        'stop_at' => $when,
                    ]);
            }
        }

        $result = $timer->stop_at
            ->lessThanOrEqualTo(self::$context->now);

        if ($result === true) {
            if (! self::$context->replaying) {
                try {
                    self::$context->storedWorkflow->logs()
                        ->create([
                            'index' => self::$context->index,
                            'now' => self::$context->now,
                            'class' => Signal::class,
                            'result' => Y::serialize($result),
                        ]);
                } catch (QueryException $exception) {
                    ++self::$context->index;
                    $deferred = new Deferred();
                    return $deferred->promise();
                }
            }
            ++self::$context->index;
            return resolve($result);
        }

        if (! self::$context->replaying) {
            Signal::dispatch(self::$context->storedWorkflow)->delay($timer->stop_at);
        }

        ++self::$context->index;
        $deferred = new Deferred();
        return $deferred->promise();
    }

    public static function now()
    {
        return self::getContext()->now;
    }

    public function id()
    {
        return $this->storedWorkflow->id;
    }

    public function logs()
    {
        return $this->storedWorkflow->logs;
    }

    public function exceptions()
    {
        return $this->storedWorkflow->exceptions;
    }

    public function output()
    {
        if ($this->storedWorkflow->fresh()->output === null) {
            return null;
        }

        return Y::unserialize($this->storedWorkflow->fresh()->output);
    }

    public function running(): bool
    {
        return ! in_array($this->status(), [WorkflowCompletedStatus::class, WorkflowFailedStatus::class], true);
    }

    public function status(): string|bool
    {
        return $this->storedWorkflow->fresh()
            ->status::class;
    }

    public function fresh(): static
    {
        $this->storedWorkflow->refresh();

        return $this;
    }

    public function restart(...$arguments): void
    {
        $this->storedWorkflow->arguments = Y::serialize($arguments);
        $this->storedWorkflow->output = null;
        $this->storedWorkflow->exceptions()
            ->delete();
        $this->storedWorkflow->logs()
            ->delete();
        $this->storedWorkflow->signals()
            ->delete();
        $this->storedWorkflow->timers()
            ->delete();

        $this->dispatch();
    }

    public function resume(): void
    {
        $this->dispatch();
    }

    public function start(...$arguments): void
    {
        $this->storedWorkflow->arguments = Y::serialize($arguments);

        $this->dispatch();
    }

    public function fail($throwable): void
    {
        try {
            $this->storedWorkflow->exceptions()
                ->create([
                    'class' => $this->storedWorkflow->class,
                    'exception' => Y::serialize($throwable),
                ]);
        } catch (\Throwable) {
        }

        $this->storedWorkflow->status->transitionTo(WorkflowFailedStatus::class);
    }

    public function next($index, $now, $class, $result): void
    {
        try {
            $this->storedWorkflow->logs()
                ->create([
                    'index' => $index,
                    'now' => $now,
                    'class' => $class,
                    'result' => Y::serialize($result),
                ]);
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'Duplicate')) {
                throw $exception;
            }
        }

        $this->dispatch();
    }

    private function dispatch(): void
    {
        $this->storedWorkflow->status->transitionTo(WorkflowPendingStatus::class);

        $this->storedWorkflow->class::dispatch(
            $this->storedWorkflow,
            ...Y::unserialize($this->storedWorkflow->arguments)
        );
    }
}
