<?php

declare(strict_types=1);

namespace Workflow;

use BadMethodCallException;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Routing\RouteDependencyResolverTrait;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use LimitIterator;
use SplFileObject;
use Throwable;
use Workflow\Middleware\WithoutOverlappingMiddleware;
use Workflow\Middleware\WorkflowMiddleware;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Y;

/**
 * @template TWorkflow of Workflow
 * @template TReturn
 */
abstract class Activity implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RouteDependencyResolverTrait;
    use SerializesModels;

    /**
     * @var int
     */
    public $tries = PHP_INT_MAX;

    /**
     * @var int
     */
    public $maxExceptions = PHP_INT_MAX;

    /**
     * @var int
     */
    public $timeout = 0;

    /**
     * @var array<int|string, mixed>
     */
    public $arguments;

    /**
     * @var string
     */
    public $key = '';

    /**
     * The container property is needed in the @see RouteDependencyResolverTrait
     * which in turn is used to dynamically resolve the "execute" method parameters.
     */
    private Container $container; // @phpstan-ignore-line

    /**
     * @param StoredWorkflow<TWorkflow, null> $storedWorkflow
     * @param mixed ...$arguments
     */
    public function __construct(
        public int $index,
        public string $now,
        public StoredWorkflow $storedWorkflow,
        ...$arguments
    ) {
        $this->arguments = $arguments;

        $this->onConnection($this->connection ?? null);

        $this->onQueue($this->queue ?? null);

        $this->afterCommit = true;
    }

    /**
     * @return int[]
     */
    public function backoff()
    {
        return [1, 2, 5, 10, 15, 30, 60, 120];
    }

    /**
     * @return int
     */
    public function workflowId()
    {
        return $this->storedWorkflow->id;
    }

    /**
     * @return void | TReturn
     */
    public function handle()
    {
        if (! method_exists($this, 'execute')) {
            throw new BadMethodCallException('Execute method not implemented.');
        }

        $this->container = App::make(Container::class);

        if ($this->storedWorkflow->logs()->whereIndex($this->index)->exists()) {
            return;
        }

        try {
            return $this->execute(...$this->resolveClassMethodDependencies($this->arguments, $this, 'execute'));
        } catch (\Throwable $throwable) {
            $this->storedWorkflow->exceptions()
                ->create([
                    'class' => $this::class,
                    'exception' => Y::serialize($throwable),
                ]);

            throw $throwable;
        }
    }

    /**
     * @return list<mixed>
     */
    public function middleware()
    {
        return [
            new WithoutOverlappingMiddleware(
                $this->storedWorkflow->id,
                WithoutOverlappingMiddleware::ACTIVITY,
                0,
                $this->timeout
            ),
            new WorkflowMiddleware(),
        ];
    }

    public function failed(Throwable $throwable): void
    {
        $workflow = $this->storedWorkflow->toWorkflow();

        $file = new SplFileObject($throwable->getFile());
        $iterator = new LimitIterator($file, max(0, $throwable->getLine() - 4), 7);

        $throwable = [
            'class' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'line' => $throwable->getLine(),
            'file' => $throwable->getFile(),
            'trace' => collect($throwable->getTrace())
                ->filter(static fn ($trace) => Y::serializable($trace))
                ->toArray(),
            'snippet' => array_slice(iterator_to_array($iterator), 0, 7),
        ];

        Exception::dispatch(
            $this->index,
            $this->now,
            $this->storedWorkflow,
            $throwable,
        )->onConnection($workflow->connection())
            ->onQueue($workflow->queue());
    }

    public function heartbeat(): void
    {
        pcntl_alarm(max($this->timeout, 0));
        if ($this->timeout > 0) {
            /**
             * NOTE: the key is set in @see WithoutOverlappingMiddleware in the lock function
             */
            Cache::put($this->key, 1, $this->timeout);
        }
    }
}
