<?php

declare(strict_types=1);

namespace Workflow;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use LimitIterator;
use ReflectionClass;
use RuntimeException;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use SplFileObject;
use stdClass;
use Throwable;
use Workflow\Events\WorkflowFailed;
use Workflow\Events\WorkflowStarted;
use Workflow\Exceptions\Transformer;
use Workflow\Models\StoredWorkflow;
use Workflow\Models\StoredWorkflowException;
use Workflow\Models\StoredWorkflowLog;
use Workflow\Serializers\Y;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowCreatedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\States\WorkflowPendingStatus;
use Workflow\Traits\Awaits;
use Workflow\Traits\AwaitWithTimeouts;
use Workflow\Traits\Fakes;
use Workflow\Traits\SideEffects;
use Workflow\Traits\Timers;

/**
 * @template TWorkflow of Workflow
 */
final class WorkflowStub
{
    use Awaits;
    use AwaitWithTimeouts;
    use Fakes;
    use Macroable;
    use SideEffects;
    use Timers;

    /**
     * @var (stdClass&object{storedWorkflow: StoredWorkflow<Workflow, null>, index: int, now: \Carbon\Carbon, replaying: bool})|null
     */
    private static ?object $context = null;

    /**
     * @param StoredWorkflow<TWorkflow, null> $storedWorkflow
     */
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

    /**
     * @param string $method
     * @param mixed[] $arguments
     * @return PendingDispatch|mixed|void
     */
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

            $this->storedWorkflow->toWorkflow();

            if (self::faked()) {
                $this->resume();
                return;
            }

            return Signal::dispatch($this->storedWorkflow, self::connection(), self::queue());
        }

        if (! collect((new ReflectionClass($this->storedWorkflow->class))->getMethods())
            ->filter(static fn ($method): bool => collect($method->getAttributes())
                ->contains(static fn ($attribute): bool => $attribute->getName() === QueryMethod::class))
            ->map(static fn ($method) => $method->getName())
            ->contains($method)
        ) {
            return;
        }

        return (new $this->storedWorkflow->class(
            $this->storedWorkflow,
            ...($this->storedWorkflow->arguments !== null ? Y::unserialize($this->storedWorkflow->arguments) : []),
        ))
            ->query($method);
    }

    public static function connection(): ?string
    {
        if (self::$context === null) {
            throw new RuntimeException('WorkflowStub::connection() must be called within a workflow');
        }

        return Arr::get(
            (new ReflectionClass(self::$context->storedWorkflow->class))->getDefaultProperties(),
            'connection'
        );
    }

    public static function queue(): ?string
    {
        if (self::$context === null) {
            throw new RuntimeException('WorkflowStub::queue() must be called within a workflow');
        }

        return Arr::get((new ReflectionClass(self::$context->storedWorkflow->class))->getDefaultProperties(), 'queue');
    }

    /**
     * @template TWorkflowClass of Workflow
     * @param class-string<TWorkflowClass> $class
     * @return self<TWorkflowClass>
     */
    public static function make($class): self
    {
        $storedWorkflow = config('workflows.stored_workflow_model', StoredWorkflow::class)::create([
            'class' => $class,
        ]);

        if (! $storedWorkflow instanceof StoredWorkflow) {
            throw new RuntimeException('StoredWorkflow model must extend ' . StoredWorkflow::class);
        }

        /** @var StoredWorkflow<TWorkflowClass, null> $storedWorkflow */
        return new self($storedWorkflow);
    }

    /**
     * @param int $id
     * @return self<Workflow>
     */
    public static function load($id)
    {
        /** @var StoredWorkflow<Workflow, null> $storedWorkflow */
        $storedWorkflow = config('workflows.stored_workflow_model', StoredWorkflow::class)::findOrFail($id);

        return self::fromStoredWorkflow($storedWorkflow);
    }

    /**
     * @template TWorkflowClass of Workflow
     * @param StoredWorkflow<TWorkflowClass, null> $storedWorkflow
     * @return self<TWorkflowClass>
     */
    public static function fromStoredWorkflow(StoredWorkflow $storedWorkflow): self
    {
        return new self($storedWorkflow);
    }

    /**
     * @return (stdClass&object{storedWorkflow: StoredWorkflow<Workflow, null>, index: int, now: \Carbon\Carbon, replaying: bool})|null
     */
    public static function getContext(): ?object
    {
        return self::$context;
    }

    /**
     * @param array{storedWorkflow: StoredWorkflow<Workflow, null>, index: int, now: \Carbon\Carbon, replaying: bool}|(stdClass&object{storedWorkflow: StoredWorkflow<Workflow, null>, index: int, now: \Carbon\Carbon, replaying: bool}) $context
     */
    public static function setContext($context): void
    {
        self::$context = (object) $context;
    }

    public static function now(): \Carbon\Carbon
    {
        if (self::getContext() === null) {
            throw new RuntimeException('WorkflowStub::now() must be called within a workflow');
        }
        return self::getContext()->now;
    }

    public function id(): int
    {
        return $this->storedWorkflow->id;
    }

    /**
     * @return Collection<int, StoredWorkflowLog>
     */
    public function logs(): Collection
    {
        return $this->storedWorkflow->logs;
    }

    /**
     * @return Collection<int, StoredWorkflowException>
     */
    public function exceptions(): Collection
    {
        return $this->storedWorkflow->exceptions;
    }

    public function output(): mixed
    {
        if ($this->storedWorkflow->fresh()?->output === null) {
            return null;
        }

        return Y::unserialize($this->storedWorkflow->fresh()->output);
    }

    public function completed(): bool
    {
        return $this->status() === WorkflowCompletedStatus::class;
    }

    public function created(): bool
    {
        return $this->status() === WorkflowCreatedStatus::class;
    }

    public function failed(): bool
    {
        return $this->status() === WorkflowFailedStatus::class;
    }

    public function running(): bool
    {
        return ! in_array($this->status(), [WorkflowCompletedStatus::class, WorkflowFailedStatus::class], true);
    }

    public function status(): string|bool
    {
        $status = $this->storedWorkflow->fresh()?->status::class;
        if ($status === null) {
            throw new RuntimeException('Could not determine workflow status.');
        }

        return $status;
    }

    public function fresh(): static
    {
        $this->storedWorkflow->refresh();

        return $this;
    }

    public function resume(): void
    {
        $this->fresh()
            ->dispatch();
    }

    /**
     * @param mixed ...$arguments
     */
    public function start(...$arguments): void
    {
        $this->storedWorkflow->arguments = Y::serialize($arguments);

        $this->dispatch();
    }

    /**
     * @param StoredWorkflow<TWorkflow, null> $parentWorkflow
     * @param mixed ...$arguments
     */
    public function startAsChild(StoredWorkflow $parentWorkflow, int $index, \Carbon\Carbon $now, ...$arguments): void
    {
        $this->storedWorkflow->parents()
            ->detach();

        $this->storedWorkflow->parents()
            ->attach($parentWorkflow, [
                'parent_index' => $index,
                'parent_now' => $now,
            ]);

        $this->start(...$arguments);
    }

    public function fail(Throwable $exception): void
    {
        try {
            $this->storedWorkflow->exceptions()
                ->create([
                    'class' => $this->storedWorkflow->class,
                    'exception' => Y::serialize(
                        app(Transformer::class)
                             ->transform($exception)
                    ),
                ]);
        } catch (QueryException) {
            // already logged
        }

        $this->storedWorkflow->status->transitionTo(WorkflowFailedStatus::class);

        $encodedException = json_encode(
            app(Transformer::class)
                ->transform($exception)
        );
        if ($encodedException === false) {
            throw new RuntimeException('Could not encode exception.');
        }
        WorkflowFailed::dispatch($this->storedWorkflow->id, $encodedException, now() ->format('Y-m-d\TH:i:s.u\Z'));

        $this->storedWorkflow->parents()
            ->each(static function ($parentWorkflow) use ($exception) {
                try {
                    $parentWorkflow->toWorkflow()
                        ->fail($exception);
                } catch (TransitionNotFound) {
                    return;
                }
            });
    }

    /**
     * @param class-string<Workflow|Activity<Workflow, mixed>> $class
     */
    public function next(int $index, \Carbon\Carbon $now, string $class, mixed $result): void
    {
        try {
            $this->storedWorkflow->logs()
                ->create([
                    'index' => $index,
                    'now' => $now,
                    'class' => $class,
                    'result' => Y::serialize($result),
                ]);
        } catch (QueryException) {
            // already logged
        }

        $this->dispatch();
    }

    private function dispatch(): void
    {
        if ($this->created()) {
            $encodedArguments = json_encode(
                $this->storedWorkflow->arguments !== null ? Y::unserialize($this->storedWorkflow->arguments) : null
            );
            if ($encodedArguments === false) {
                throw new RuntimeException('Could not encode arguments.');
            }

            WorkflowStarted::dispatch(
                $this->storedWorkflow->id,
                $this->storedWorkflow->class,
                $encodedArguments,
                now()
                    ->format('Y-m-d\TH:i:s.u\Z')
            );
        }

        $this->storedWorkflow->status->transitionTo(WorkflowPendingStatus::class);

        if (self::faked()) {
            $this->storedWorkflow->class::dispatchSync(
                $this->storedWorkflow,
                ...($this->storedWorkflow->arguments !== null ? Y::unserialize($this->storedWorkflow->arguments) : [])
            );
        } else {
            $this->storedWorkflow->class::dispatch(
                $this->storedWorkflow,
                ...($this->storedWorkflow->arguments !== null ? Y::unserialize($this->storedWorkflow->arguments) : [])
            );
        }
    }
}
