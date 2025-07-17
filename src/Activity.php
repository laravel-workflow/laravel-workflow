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
use Illuminate\Support\Str;
use LimitIterator;
use SplFileObject;
use Throwable;
use Workflow\Exceptions\NonRetryableExceptionContract;
use Workflow\Middleware\ActivityMiddleware;
use Workflow\Middleware\WithoutOverlappingMiddleware;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;

/**
 * Class Activity - A dispatchable job that will be dispatched to a Laravel queue for a worker to process
 *
 * This is an abstract class the should be extended by your own activity classes. This base class represents a
 * dispatchable job that will be dispatched to a Laravel queue for a worker to process.
 *
 * When instantiated and dispatched to a queue, this class receives the following properties:
 * - $index: The index of the activity in the workflow. Each time a workflow yields to wait for an activity, child
 * workflow, or side effect, this index is incremented.
 * - $now: The "current" time in ISO 8601 format. The calling workflow tracks the time when the activity is
 * dispatched and that time can be used in the activity logic. This allows retried activities to be retried using
 * the same time value.
 * - $storedWorkflow: The database model representing the workflow from which this activity was dispatched.
 * - ...$arguments: The arguments passed to execute() method of the class that extends this class.
 *
 * Note: you should never instantiate this class or its children directly. Instead, you should call it from a workflow
 * class using the `ActivityStub::make(YourChildActivityClass::class, ...$arguments)` method.
 */
class Activity implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RouteDependencyResolverTrait;
    use SerializesModels;

    public $tries = PHP_INT_MAX;

    public $maxExceptions = PHP_INT_MAX;

    public $timeout = 0;

    public $arguments;

    public $key = '';

    private Container $container;

    public function __construct(
        public int $index,
        public string $now,
        public StoredWorkflow $storedWorkflow,
        ...$arguments
    ) {
        $this->arguments = $arguments;

        if (property_exists($this, 'connection')) {
            $this->onConnection($this->connection);
        }

        if (property_exists($this, 'queue')) {
            $this->onQueue($this->queue);
        }

        $this->afterCommit = true;
    }

    public function backoff()
    {
        return [1, 2, 5, 10, 15, 30, 60, 120];
    }

    public function workflowId()
    {
        return $this->storedWorkflow->id;
    }

    public function webhookUrl(string $signalMethod = ''): string
    {
        $basePath = config('workflows.webhooks_route', '/webhooks');
        if ($signalMethod === '') {
            $workflow = Str::kebab(class_basename($this->storedWorkflow->class));
            return url("{$basePath}/{$workflow}");
        }
        $signal = Str::kebab($signalMethod);
        return url("{$basePath}/signal/{$this->storedWorkflow->id}/{$signal}");
    }

    public function handle()
    {
        // If the child class does not implement the execute method, throw an exception
        if (! method_exists($this, 'execute')) {
            throw new BadMethodCallException('Execute method not implemented.');
        }

        $this->container = App::make(Container::class);

        // If this activity has already been executed and a return value has been stored in the database, then
        // return. The middleware will handle dispatching its parent workflow to continue the execution of the
        // workflow.
        if ($this->storedWorkflow->logs()->whereIndex($this->index)->exists()) {
            return;
        }

        // Execute the child class's execute method, passing in the $...arguments that were passed to the
        // ActivityStub::make() method. If the child class throws an exception, it will be caught here and
        // the exception will be stored in the database. If the exception is a NonRetryableExceptionContract,
        // then the workflow will be failed. If the exception is not a NonRetryableExceptionContract, then
        // the exception will be rethrown, caught by the middleware, and the activity will be retried.
        try {
            return $this->{'execute'}(...$this->resolveClassMethodDependencies($this->arguments, $this, 'execute'));
        } catch (\Throwable $throwable) {
            $this->storedWorkflow->exceptions()
                ->create([
                    'class' => $this::class,
                    'exception' => Serializer::serialize($throwable),
                ]);

            if ($throwable instanceof NonRetryableExceptionContract) {
                $this->fail($throwable);
            }

            throw $throwable;
        }
    }

    public function middleware()
    {
        return [
            // Ensure that this activity cannot run while the workflow is executing
            new WithoutOverlappingMiddleware(
                $this->storedWorkflow->id,
                WithoutOverlappingMiddleware::ACTIVITY,
                0,
                $this->timeout
            ),
            // Dispatch activity lifecycle events, execute the activity, and store the activity output in the database
            new ActivityMiddleware(),
        ];
    }

    /**
     * This method is called when the activity throws an exception that is a NonRetryableExceptionContract or
     * if the activity throws an exception that is not a NonRetryableExceptionContract but the activity is not
     * retryable (i.e. it has been released back to the queue more than $tries times).
     */
    public function failed(Throwable $throwable): void
    {
        // Instantiate the WorkflowStub class for the workflow from which this activity was dispatched
        $workflow = $this->storedWorkflow->toWorkflow();

        // Build a serializable version of the exception
        $file = new SplFileObject($throwable->getFile());
        $iterator = new LimitIterator($file, max(0, $throwable->getLine() - 4), 7);

        $throwable = [
            'class' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'line' => $throwable->getLine(),
            'file' => $throwable->getFile(),
            'trace' => collect($throwable->getTrace())
                ->filter(static fn ($trace) => Serializer::serializable($trace))
                ->toArray(),
            'snippet' => array_slice(iterator_to_array($iterator), 0, 7),
        ];

        // Dispatch a job to store the exception in the database and to dispatch the parent workflow to continue
        // the execution of the workflow.
        Exception::dispatch(
            $this->index,
            $this->now,
            $this->storedWorkflow,
            $throwable,
            $workflow->connection(),
            $workflow->queue()
        );
    }

    public function heartbeat(): void
    {
        pcntl_alarm(max($this->timeout, 0));
        if ($this->timeout) {
            Cache::put($this->key, 1, $this->timeout);
        }
    }
}
