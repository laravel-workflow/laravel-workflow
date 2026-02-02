<?php

declare(strict_types=1);

namespace Workflow;

use BadMethodCallException;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Routing\RouteDependencyResolverTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use React\Promise\PromiseInterface;
use Throwable;
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

class Workflow implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RouteDependencyResolverTrait;
    use Sagas;
    use SerializesModels;

    public ?string $key = null;

    public int $tries = 0;

    public int $maxExceptions = 0;

    public $arguments;

    public $coroutine;

    public int $index = 0;

    public $now;

    public bool $replaying = false;

    public Inbox $inbox;

    public Outbox $outbox;

    private Container $container;

    public function __construct(
        public StoredWorkflow $storedWorkflow,
        ...$arguments
    ) {
        $this->inbox = new Inbox();

        $this->outbox = new Outbox();

        $this->arguments = $arguments;

        if (property_exists($this, 'connection')) {
            $this->onConnection($this->connection);
        }

        if (property_exists($this, 'queue')) {
            $this->onQueue($this->queue);
        }

        $this->afterCommit = true;
    }

    public function uniqueId()
    {
        return $this->storedWorkflow->id;
    }

    public function query($method)
    {
        $this->replaying = true;
        $this->handle();
        return $this->{$method}();
    }

    public function child(): ?ChildWorkflowHandle
    {
        $storedChild = $this->storedWorkflow->children()
            ->wherePivot('parent_index', '<', WorkflowStub::getContext()->index)
            ->orderByDesc('child_workflow_id')
            ->first();

        return $storedChild ? new ChildWorkflowHandle($storedChild) : null;
    }

    public function children(): array
    {
        return $this->storedWorkflow->children()
            ->wherePivot('parent_index', '<', WorkflowStub::getContext()->index)
            ->orderByDesc('child_workflow_id')
            ->get()
            ->map(static fn ($child) => new ChildWorkflowHandle($child))
            ->toArray();
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
        try {
            $this->storedWorkflow->toWorkflow()
                ->fail($throwable);
        } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
            return;
        }
    }

    public function handle(): void
    {
        if (! method_exists($this, 'execute')) {
            throw new BadMethodCallException('Execute method not implemented.');
        }

        $this->container = App::make(Container::class);

        try {
            if (! $this->replaying) {
                $this->storedWorkflow->status->transitionTo(WorkflowRunningStatus::class);
            }
        } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
            if ($this->storedWorkflow->toWorkflow()->running()) {
                $this->release();
            }
            return;
        }

        $parentWorkflow = $this->storedWorkflow->parents()
            ->wherePivot('parent_index', '!=', StoredWorkflow::CONTINUE_PARENT_INDEX)
            ->wherePivot('parent_index', '!=', StoredWorkflow::ACTIVE_WORKFLOW_INDEX)
            ->first();

        $replayedSignalIds = [];

        $logs = $this->storedWorkflow->logs()
            ->whereIn('index', [$this->index, $this->index + 1])
            ->get();

        $log = $logs->where('index', $this->index)
            ->first();

        $nextLog = $logs->where('index', $this->index + 1)
            ->first();

        $initialSignalBound = $nextLog ? $nextLog->created_at : null;

        $this->storedWorkflow
            ->signals()
            ->when($nextLog, static function ($query, $nextLog): void {
                $query->where('created_at', '<=', $nextLog->created_at->format('Y-m-d H:i:s.u'));
            })
            ->each(function ($signal): void {
                $this->{$signal->method}(...Serializer::unserialize($signal->arguments));
            });

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

            $logs = $this->storedWorkflow->logs()
                ->whereIn('index', [$this->index, $this->index + 1])
                ->get();

            $log = $logs->where('index', $this->index)
                ->first();

            $nextLog = $logs->where('index', $this->index + 1)
                ->first();

            if ($log) {
                $this->storedWorkflow
                    ->signals()
                    ->where('created_at', '>', $log->created_at->format('Y-m-d H:i:s.u'))
                    ->when($nextLog, static function ($query, $nextLog): void {
                        $query->where('created_at', '<=', $nextLog->created_at->format('Y-m-d H:i:s.u'));
                    })
                    ->each(function ($signal) use (&$replayedSignalIds): void {
                        if (! in_array($signal->id, $replayedSignalIds, true)) {
                            $replayedSignalIds[] = $signal->id;
                            $this->{$signal->method}(...Serializer::unserialize($signal->arguments));
                        }
                    });
            } elseif ($initialSignalBound) {
                $this->storedWorkflow
                    ->signals()
                    ->where('created_at', '>', $initialSignalBound->format('Y-m-d H:i:s.u'))
                    ->each(function ($signal) use (&$replayedSignalIds): void {
                        if (! in_array($signal->id, $replayedSignalIds, true)) {
                            // @codeCoverageIgnoreStart
                            $replayedSignalIds[] = $signal->id;
                            $this->{$signal->method}(...Serializer::unserialize($signal->arguments));
                            // @codeCoverageIgnoreEnd
                        }
                    });
            }

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
                $exception = null;

                $current->then(function ($value) use (&$resolved, &$exception): void {
                    $resolved = true;
                    try {
                        $this->coroutine->send($value);
                    } catch (Throwable $th) {
                        $exception = $th;
                    }
                });

                if ($exception) {
                    throw $exception;
                }

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
                $properties = WorkflowStub::getDefaultProperties($parentWorkflow->class);
                $connection = $properties['connection'] ?? config('queue.default');
                $queue = $properties['queue'] ?? config('queue.connections.' . $connection . '.queue', 'default');

                ChildWorkflow::dispatch(
                    $parentWorkflow->pivot->parent_index,
                    $this->now,
                    $this->storedWorkflow,
                    $return,
                    $parentWorkflow,
                    $connection,
                    $queue
                );
            }
        }
    }
}
