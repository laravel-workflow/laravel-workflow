<?php

declare(strict_types=1);

namespace Workflow;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Traits\Macroable;
use LimitIterator;
use ReflectionClass;
use SplFileObject;
use Workflow\Domain\Contracts\ExceptionHandlerInterface;
use Workflow\Events\WorkflowFailed;
use Workflow\Events\WorkflowStarted;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowCreatedStatus;
use Workflow\States\WorkflowFailedStatus;
use Workflow\States\WorkflowPendingStatus;
use Workflow\Traits\Awaits;
use Workflow\Traits\AwaitWithTimeouts;
use Workflow\Traits\Continues;
use Workflow\Traits\Fakes;
use Workflow\Traits\SideEffects;
use Workflow\Traits\Timers;

final class WorkflowStub
{
    use Awaits;
    use AwaitWithTimeouts;
    use Continues;
    use Fakes;
    use Macroable;
    use SideEffects;
    use Timers;

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
            $activeWorkflow = $this->storedWorkflow->active();

            $activeWorkflow->signals()
                ->create([
                    'method' => $method,
                    'arguments' => Serializer::serialize($arguments),
                ]);

            $activeWorkflow->toWorkflow();

            if (static::faked()) {
                $this->resume();
                return;
            }

            return Signal::dispatch($activeWorkflow, self::connection(), self::queue());
        }

        if (collect((new ReflectionClass($this->storedWorkflow->class))->getMethods())
            ->filter(static fn ($method): bool => collect($method->getAttributes())
                ->contains(static fn ($attribute): bool => $attribute->getName() === QueryMethod::class))
            ->map(static fn ($method) => $method->getName())
            ->contains($method)
        ) {
            $activeWorkflow = $this->storedWorkflow->active();

            return (new $activeWorkflow->class(
                $activeWorkflow,
                ...Serializer::unserialize($activeWorkflow->arguments),
            ))
                ->query($method);
        }
    }

    public static function connection()
    {
        return Arr::get(
            (new ReflectionClass(self::$context->storedWorkflow->class))->getDefaultProperties(),
            'connection'
        );
    }

    public static function queue()
    {
        return Arr::get((new ReflectionClass(self::$context->storedWorkflow->class))->getDefaultProperties(), 'queue');
    }

    public static function make($class): static
    {
        file_put_contents('php://stderr', "[WorkflowStub::make] ENTERED for class: {$class}\n");
        echo "[WorkflowStub::make] ENTERED for class: {$class}\n";
        flush();

        file_put_contents('php://stderr', "[WorkflowStub::make] Getting config for stored_workflow_model\n");
        $modelClass = config('workflows.stored_workflow_model', StoredWorkflow::class);
        file_put_contents('php://stderr', "[WorkflowStub::make] Model class: {$modelClass}\n");

        file_put_contents('php://stderr', "[WorkflowStub::make] About to call {$modelClass}::create()\n");
        flush();

        $storedWorkflow = $modelClass::create([
            'class' => $class,
        ]);

        file_put_contents(
            'php://stderr',
            "[WorkflowStub::make] Created stored workflow with ID: {$storedWorkflow->id}\n"
        );
        echo "[WorkflowStub::make] Created stored workflow with ID: {$storedWorkflow->id}\n";
        flush();

        file_put_contents('php://stderr', "[WorkflowStub::make] About to return new self\n");
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
        return $this->storedWorkflow->active()
            ->logs;
    }

    public function exceptions()
    {
        return $this->storedWorkflow->active()
            ->exceptions;
    }

    public function output()
    {
        $activeWorkflow = $this->storedWorkflow->active();

        if ($activeWorkflow->output === null) {
            return null;
        }

        return Serializer::unserialize($activeWorkflow->output);
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
        $status = $this->status();
        $isRunning = ! in_array($status, [WorkflowCompletedStatus::class, WorkflowFailedStatus::class], true);

        if (getenv('GITHUB_ACTIONS') === 'true') {
            static $logCount = 0;
            if ($logCount % 50 === 0) {
                echo "[WorkflowStub::running] Check #{$logCount} - Status: {$status}, Running: " . ($isRunning ? 'true' : 'false') . "\n";
            }
            $logCount++;
        }

        return $isRunning;
    }

    public function status(): string|bool
    {
        return $this->storedWorkflow->active()
            ->status::class;
    }

    public function fresh(): static
    {
        try {
            $this->storedWorkflow->refresh();
        } catch (\Illuminate\Database\Eloquent\RelationNotFoundException $e) {
            // Pivot relation not found during refresh - reload without relations
            // This can happen with certain database backends when eager loading
            $this->storedWorkflow = $this->storedWorkflow->fresh();
        }

        return $this;
    }

    public function resume(): void
    {
        $this->fresh()
            ->dispatch();
    }

    public function start(...$arguments): void
    {
        file_put_contents(
            'php://stderr',
            "[WorkflowStub::start] ENTERED for workflow ID: {$this->storedWorkflow->id}\n"
        );
        echo "[WorkflowStub::start] ENTERED for workflow ID: {$this->storedWorkflow->id}\n";
        flush();

        file_put_contents('php://stderr', "[WorkflowStub::start] Serializing arguments\n");
        $this->storedWorkflow->arguments = Serializer::serialize($arguments);
        file_put_contents('php://stderr', "[WorkflowStub::start] Arguments serialized\n");

        file_put_contents('php://stderr', "[WorkflowStub::start] About to call dispatch()\n");
        echo "[WorkflowStub::start] About to call dispatch()\n";
        flush();

        $this->dispatch();

        file_put_contents('php://stderr', "[WorkflowStub::start] dispatch() returned\n");
        echo "[WorkflowStub::start] dispatch() returned\n";
        flush();
    }

    public function startAsChild(StoredWorkflow $parentWorkflow, int $index, $now, ...$arguments): void
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

    public function fail($exception): void
    {
        try {
            $this->storedWorkflow->exceptions()
                ->create([
                    'class' => $this->storedWorkflow->class,
                    'exception' => Serializer::serialize($exception),
                ]);
        } catch (\Throwable $e) {
            $exceptionHandler = app(\Workflow\Domain\Contracts\ExceptionHandlerInterface::class);
            // Ignore duplicate key errors - exception already recorded
            if (! $exceptionHandler->isDuplicateKeyException($e)) {
                throw $e;
            }
        }

        $this->storedWorkflow->status->transitionTo(WorkflowFailedStatus::class);

        $file = new SplFileObject($exception->getFile());
        $iterator = new LimitIterator($file, max(0, $exception->getLine() - 4), 7);

        WorkflowFailed::dispatch($this->storedWorkflow->id, json_encode([
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'line' => $exception->getLine(),
            'file' => $exception->getFile(),
            'trace' => $exception->getTrace(),
            'snippet' => array_slice(iterator_to_array($iterator), 0, 7),
        ]), now()
            ->format('Y-m-d\TH:i:s.u\Z'));

        $this->storedWorkflow->parents()
            ->each(static function ($parentWorkflow) use ($exception) {
                // Skip if parent workflow doesn't exist
                if ($parentWorkflow === null) {
                    return;
                }

                try {
                    $parentWorkflow->toWorkflow()
                        ->fail($exception);
                } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
                    return;
                }
            });
    }

    public function next($index, $now, $class, $result): void
    {
        try {
            $this->storedWorkflow->logs()
                ->create([
                    'index' => $index,
                    'now' => $now,
                    'class' => $class,
                    'result' => Serializer::serialize($result),
                ]);
        } catch (\Throwable $exception) {
            $exceptionHandler = app(ExceptionHandlerInterface::class);

            if (! $exceptionHandler->isDuplicateKeyException($exception)) {
                throw $exception;
            }
            // Already logged - duplicate key is expected in replay scenarios
        }

        $this->dispatch();
    }

    private function dispatch(): void
    {
        file_put_contents('php://stderr', "[WorkflowStub::dispatch] ENTERED\n");
        echo "[WorkflowStub::dispatch] ENTERED\n";
        flush();

        file_put_contents('php://stderr', "[WorkflowStub::dispatch] Getting status\n");
        $status = $this->status();
        file_put_contents('php://stderr', "[WorkflowStub::dispatch] Status: {$status}\n");

        file_put_contents('php://stderr', "[WorkflowStub::dispatch] Checking if created()\n");
        if ($this->created()) {
            file_put_contents(
                'php://stderr',
                "[WorkflowStub::dispatch] Workflow is created, dispatching WorkflowStarted event\n"
            );

            WorkflowStarted::dispatch(
                $this->storedWorkflow->id,
                $this->storedWorkflow->class,
                json_encode(Serializer::unserialize($this->storedWorkflow->arguments)),
                now()
                    ->format('Y-m-d\TH:i:s.u\Z')
            );

            file_put_contents('php://stderr', "[WorkflowStub::dispatch] WorkflowStarted event dispatched\n");
        }

        file_put_contents('php://stderr', "[WorkflowStub::dispatch] Transitioning to WorkflowPendingStatus\n");

        $this->storedWorkflow->status->transitionTo(WorkflowPendingStatus::class);

        file_put_contents('php://stderr', "[WorkflowStub::dispatch] Status transitioned\n");

        $dispatch = static::faked() ? 'dispatchSync' : 'dispatch';

        file_put_contents('php://stderr', "[WorkflowStub::dispatch] Dispatch method: {$dispatch}\n");
        file_put_contents(
            'php://stderr',
            '[WorkflowStub::dispatch] Queue connection: ' . config('queue.default') . "\n"
        );
        file_put_contents(
            'php://stderr',
            "[WorkflowStub::dispatch] About to dispatch workflow class: {$this->storedWorkflow->class}\n"
        );
        file_put_contents('php://stderr', "[WorkflowStub::dispatch] Using dispatch method: {$dispatch}\n");
        flush();

        // Log queue manager state before dispatch
        $queueManager = app('queue');
        $connection = $queueManager->connection();
        file_put_contents(
            'php://stderr',
            '[WorkflowStub::dispatch] Queue manager connection class: ' . get_class($connection) . "\n"
        );
        file_put_contents(
            'php://stderr',
            '[WorkflowStub::dispatch] Queue connection name: ' . $connection->getConnectionName() . "\n"
        );

        $this->storedWorkflow->class::$dispatch(
            $this->storedWorkflow,
            ...Serializer::unserialize($this->storedWorkflow->arguments)
        );

        file_put_contents('php://stderr', "[WorkflowStub::dispatch] Workflow class dispatched\n");

        // Check if there are pending transactions
        try {
            $dbConnection = app('db')
                ->connection();
            $transactionLevel = $dbConnection->transactionLevel();
            file_put_contents(
                'php://stderr',
                "[WorkflowStub::dispatch] Database transaction level: {$transactionLevel}\n"
            );
        } catch (\Exception $e) {
            file_put_contents(
                'php://stderr',
                '[WorkflowStub::dispatch] Could not check transaction level: ' . $e->getMessage() . "\n"
            );
        }

        // Check Redis queue to verify job was queued
        try {
            $redis = new \Redis();
            $redis->connect(
                config('database.redis.default.host', '127.0.0.1'),
                (int) config('database.redis.default.port', 6379)
            );
            // Check multiple possible queue key patterns
            $patterns = ['queues:default', 'laravel_database_queues:default', 'laravel:queues:default'];

            foreach ($patterns as $pattern) {
                $size = $redis->lLen($pattern);
                file_put_contents('php://stderr', "[WorkflowStub::dispatch] Redis queue '{$pattern}' size: {$size}\n");
            }

            // List all keys to see what's actually in Redis
            $allKeys = $redis->keys('*');
            file_put_contents(
                'php://stderr',
                '[WorkflowStub::dispatch] All Redis keys: ' . implode(', ', $allKeys) . "\n"
            );

            $redis->close();
        } catch (\Exception $e) {
            file_put_contents(
                'php://stderr',
                '[WorkflowStub::dispatch] Redis check failed: ' . $e->getMessage() . "\n"
            );
        }

        echo "[WorkflowStub::dispatch] Workflow class dispatched\n";
        flush();
    }
}
