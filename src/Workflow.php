<?php

declare(strict_types=1);

namespace Workflow;

use BadMethodCallException;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Routing\RouteDependencyResolverTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use React\Promise\PromiseInterface;
use Throwable;
use Workflow\Domain\Contracts\QueryAdapterInterface;
use Workflow\Events\WorkflowCompleted;
use Workflow\Middleware\WithoutOverlappingMiddleware;
use Workflow\Models\StoredWorkflow;
use Workflow\Serializers\Serializer;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowContinuedStatus;
use Workflow\States\WorkflowRunningStatus;
use Workflow\States\WorkflowWaitingStatus;
use Workflow\Traits\Sagas;
use Workflow\Traits\SerializesModels;

class Workflow implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RouteDependencyResolverTrait;
    use Sagas;
    use SerializesModels;

    public int $tries = 0;

    public int $maxExceptions = 0;

    public $arguments;

    public $coroutine;

    public int $index = 0;

    public $now;

    public bool $replaying = false;

    private Container $container;

    public function __construct(
        public StoredWorkflow $storedWorkflow,
        ...$arguments
    ) {
        file_put_contents(
            'php://stderr',
            '[Workflow::__construct] ENTERED for workflow class: ' . static::class . ", ID: {$storedWorkflow->id}\n"
        );

        $this->arguments = $arguments;

        if (property_exists($this, 'connection')) {
            $this->onConnection($this->connection);
        }

        if (property_exists($this, 'queue')) {
            $this->onQueue($this->queue);
        }

        $this->afterCommit = true;

        file_put_contents('php://stderr', "[Workflow::__construct] FINISHED\n");
    }

    public function query($method)
    {
        $this->replaying = true;
        $this->handle();
        return $this->{$method}();
    }

    public function middleware()
    {
        $parentWorkflow = $this->storedWorkflow->parents()
            ->wherePivot('parent_index', '!=', StoredWorkflow::CONTINUE_PARENT_INDEX)
            ->wherePivot('parent_index', '!=', StoredWorkflow::ACTIVE_WORKFLOW_INDEX)
            ->first();

        if ($parentWorkflow) {
            return [
                new WithoutOverlappingMiddleware($parentWorkflow->id, WithoutOverlappingMiddleware::ACTIVITY, 0, 15),
            ];
        }
        return [
            new WithoutOverlappingMiddleware($this->storedWorkflow->id, WithoutOverlappingMiddleware::WORKFLOW, 0, 15),
        ];
    }

    public function failed(Throwable $throwable): void
    {
        file_put_contents(
            'php://stderr',
            '[Workflow::failed] Job failed with exception: ' . $throwable->getMessage() . "\n"
        );
        file_put_contents('php://stderr', '[Workflow::failed] Exception class: ' . get_class($throwable) . "\n");
        file_put_contents('php://stderr', '[Workflow::failed] Trace: ' . $throwable->getTraceAsString() . "\n");

        try {
            $this->storedWorkflow->toWorkflow()
                ->fail($throwable);
        } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
            return;
        }
    }

    public function handle(): void
    {
        file_put_contents(
            'php://stderr',
            "[Workflow::handle] ENTERED for workflow ID: {$this->storedWorkflow->id}, Class: " . static::class . "\n"
        );
        echo "[Workflow::handle] ENTERED for workflow ID: {$this->storedWorkflow->id}, Class: " . static::class

         . "\n";
        flush();

        if (! method_exists($this, 'execute')) {
            throw new BadMethodCallException('Execute method not implemented.');
        }

        file_put_contents('php://stderr', "[Workflow::handle] Creating container\n");
        $this->container = App::make(Container::class);
        file_put_contents('php://stderr', "[Workflow::handle] Container created\n");

        file_put_contents('php://stderr', "[Workflow::handle] About to transition to WorkflowRunningStatus\n");

        try {
            if (! $this->replaying) {
                $this->storedWorkflow->status->transitionTo(WorkflowRunningStatus::class);
            }

            file_put_contents(
                'php://stderr',
                "[Workflow::handle] Transitioned to WorkflowRunningStatus successfully\n"
            );
        } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound $e) {
            file_put_contents(
                'php://stderr',
                '[Workflow::handle] TransitionNotFound exception caught: ' . $e->getMessage() . "\n"
            );

            if ($this->storedWorkflow->toWorkflow()->running()) {
                file_put_contents(
                    'php://stderr',
                    "[Workflow::handle] Workflow already running, releasing back to queue\n"
                );
                $this->release();
            }
            return;
        } catch (\Exception $e) {
            file_put_contents(
                'php://stderr',
                '[Workflow::handle] EXCEPTION during transition: ' . $e->getMessage() . "\n"
            );
            file_put_contents('php://stderr', '[Workflow::handle] Exception trace: ' . $e->getTraceAsString() . "\n");
            throw $e;
        }

        $parentWorkflow = $this->storedWorkflow->parents()
            ->wherePivot('parent_index', '!=', StoredWorkflow::CONTINUE_PARENT_INDEX)
            ->wherePivot('parent_index', '!=', StoredWorkflow::ACTIVE_WORKFLOW_INDEX)
            ->first();

        $log = $this->storedWorkflow->logs()
            ->whereIndex($this->index)
            ->first();

        $queryAdapter = app(QueryAdapterInterface::class);
        $signals = $queryAdapter->getSignalsUpToTimestamp($this->storedWorkflow, $log?->created_at);

        foreach ($signals as $signal) {
            $this->{$signal->method}(...Serializer::unserialize($signal->arguments));
        }

        if ($parentWorkflow) {
            $this->now = Carbon::parse($parentWorkflow->pivot->parent_now);
        } else {
            $this->now = $log ? $log->now : Carbon::now();
        }

        WorkflowStub::setContext([
            'storedWorkflow' => $this->storedWorkflow,
            'index' => $this->index,
            'now' => $this->now,
            'replaying' => $this->replaying,
        ]);

        $this->coroutine = $this->{'execute'}(...$this->resolveClassMethodDependencies(
            $this->arguments,
            $this,
            'execute'
        ));

        while ($this->coroutine->valid()) {
            $this->index = WorkflowStub::getContext()->index;

            $nextLog = $this->storedWorkflow->logs()
                ->whereIndex($this->index)
                ->first();

            if ($log) {
                $signals = $queryAdapter->getSignalsBetweenTimestamps(
                    $this->storedWorkflow,
                    $log->created_at,
                    $nextLog?->created_at
                );

                foreach ($signals as $signal) {
                    $this->{$signal->method}(...Serializer::unserialize($signal->arguments));
                }
            }

            $log = $nextLog;

            $this->now = $log ? $log->now : Carbon::now();

            WorkflowStub::setContext([
                'storedWorkflow' => $this->storedWorkflow,
                'index' => $this->index,
                'now' => $this->now,
                'replaying' => $this->replaying,
            ]);

            $current = $this->coroutine->current();

            if ($current instanceof PromiseInterface) {
                $resolved = false;

                $current->then(function ($value) use (&$resolved): void {
                    $resolved = true;
                    $this->coroutine->send($value);
                });

                if (! $resolved) {
                    if (! $this->replaying) {
                        $this->storedWorkflow->status->transitionTo(WorkflowWaitingStatus::class);
                    }

                    return;
                }
            } else {
                throw new Exception('something went wrong');
            }
        }

        if (! $this->replaying) {
            try {
                $return = $this->coroutine->getReturn();
            } catch (Throwable $th) {
                throw new Exception('Workflow failed.', 0, $th);
            }

            if ($return instanceof ContinuedWorkflow) {
                $this->storedWorkflow->status->transitionTo(WorkflowContinuedStatus::class);
                return;
            }

            $this->storedWorkflow->output = Serializer::serialize($return);

            $this->storedWorkflow->status->transitionTo(WorkflowCompletedStatus::class);

            WorkflowCompleted::dispatch(
                $this->storedWorkflow->id,
                json_encode($return),
                now()
                    ->format('Y-m-d\TH:i:s.u\Z')
            );

            if ($parentWorkflow) {
                ChildWorkflow::dispatch(
                    $parentWorkflow->pivot->parent_index,
                    $this->now,
                    $this->storedWorkflow,
                    $return,
                    $parentWorkflow
                );
            }
        }
    }
}
