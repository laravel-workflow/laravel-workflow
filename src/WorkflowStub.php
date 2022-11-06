<?php

declare(strict_types=1);

namespace Workflow;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use ReflectionClass;
use Workflow\Models\StoredWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\States\WorkflowPendingStatus;

final class WorkflowStub
{
    private static ?\stdClass $context = null;

    private function __construct(
        protected $storedWorkflow
    ) {
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
                    'arguments' => serialize($arguments),
                ]);

            Signal::dispatch($this->storedWorkflow);
        }
    }

    public static function make($class): static
    {
        $storedWorkflow = StoredWorkflow::create([
            'class' => $class,
        ]);

        return new self($storedWorkflow);
    }

    public static function load($id)
    {
        return static::fromStoredWorkflow(StoredWorkflow::findOrFail($id));
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
            return resolve(true);
        }

        $deferred = new Deferred();

        return $deferred->promise();
    }

    public static function awaitWithTimeout($seconds, $condition): PromiseInterface
    {
        $result = $condition();

        if ($result === true) {
            return resolve(true);
        }

        return self::timer($seconds)->then(static fn ($completed): bool => ! $completed);
    }

    public static function timer($seconds): PromiseInterface
    {
        if ($seconds <= 0) {
            return resolve(true);
        }

        $context = self::getContext();

        $timer = $context->storedWorkflow->timers()
            ->whereIndex($context->index)
            ->first();

        if ($timer === null) {
            $when = now()
                ->addSeconds($seconds);

            $timer = $context->storedWorkflow->timers()
                ->create([
                    'index' => $context->index,
                    'stop_at' => $when,
                ]);
        } else {
            $result = $timer->stop_at->lessThanOrEqualTo(now()->addSeconds($seconds));

            if ($result === true) {
                return resolve(true);
            }
        }

        Signal::dispatch($context->storedWorkflow)->delay($timer->stop_at);

        $deferred = new Deferred();

        return $deferred->promise();
    }

    public function id()
    {
        return $this->storedWorkflow->id;
    }

    public function output()
    {
        return unserialize($this->storedWorkflow->fresh()->output);
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
        $this->storedWorkflow->arguments = serialize($arguments);
        $this->storedWorkflow->output = null;
        $this->storedWorkflow->logs()
            ->delete();

        $this->dispatch();
    }

    public function resume(): void
    {
        $this->dispatch();
    }

    public function start(...$arguments): void
    {
        $this->storedWorkflow->arguments = serialize($arguments);

        $this->dispatch();
    }

    public function fail($exception): void
    {
        $this->storedWorkflow->output = serialize((string) $exception);

        $this->storedWorkflow->status->transitionTo(WorkflowFailedStatus::class);
    }

    public function next($index, $result): void
    {
        $this->storedWorkflow->logs()
            ->create([
                'index' => $index,
                'result' => serialize($result),
            ]);

        $this->dispatch();
    }

    private function dispatch(): void
    {
        $this->storedWorkflow->status->transitionTo(WorkflowPendingStatus::class);

        $this->storedWorkflow->class::dispatch($this->storedWorkflow, ...unserialize($this->storedWorkflow->arguments));
    }
}
