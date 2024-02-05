<?php

declare(strict_types=1);

namespace Workflow;

use BadMethodCallException;
use Exception;
use Generator;
use Illuminate\Bus\Queueable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Routing\RouteDependencyResolverTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use React\Promise\PromiseInterface;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Throwable;
use Workflow\Events\WorkflowCompleted;
use Workflow\Middleware\WithoutOverlappingMiddleware;
use Workflow\Models\StoredWorkflow;
use Workflow\Models\StoredWorkflowLog;
use Workflow\Models\StoredWorkflowSignal;
use Workflow\Serializers\Y;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowRunningStatus;
use Workflow\States\WorkflowWaitingStatus;
use Workflow\Traits\Sagas;

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

    /**
     * @var mixed[]
     */
    public $arguments;

    /**
     * @var string
     */
    public $key = '';

    /**
     * @var Generator<int, mixed, mixed, mixed>
     */
    public $coroutine;

    public int $index = 0;

    /**
     * @var \Carbon\Carbon
     */
    public $now;

    public bool $replaying = false;

    /**
     * The container property is needed in the @see RouteDependencyResolverTrait
     * which in turn is used to dynamically resolve the "execute" method parameters.
     */
    private Container $container; //@phpstan-ignore-line

    /**
     * @param StoredWorkflow<self, null> $storedWorkflow
     * @param mixed ...$arguments
     */
    public function __construct(
        public StoredWorkflow $storedWorkflow,
        ...$arguments
    ) {
        $this->arguments = $arguments;

        $this->onConnection($this->connection ?? null);

        $this->onQueue($this->queue ?? null);

        $this->afterCommit = true;
    }

    /**
     * @return mixed
     */
    public function query(string $method)
    {
        $this->replaying = true;
        $this->handle();
        return $this->{$method}();
    }

    /**
     * @return mixed[]
     */
    public function middleware()
    {
        $parentWorkflow = $this->storedWorkflow->parents()
            ->first();

        if ($parentWorkflow !== null) {
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
        } catch (TransitionNotFound) {
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
        } catch (TransitionNotFound) {
            if ($this->storedWorkflow->toWorkflow()->running()) {
                $this->release();
            }
            return;
        }

        $parentWorkflow = $this->storedWorkflow->parents()
            ->first();

        $log = $this->storedWorkflow->logs()
            ->whereIndex($this->index)
            ->first();

        $this->storedWorkflow
            ->signals()
            ->when($log, static function ($query, StoredWorkflowLog $log): void {
                if ($log->created_at === null) {
                    throw new \RuntimeException('The Log must have a created_at timestamp.');
                }
                $query->where('created_at', '<=', $log->created_at->format('Y-m-d H:i:s.u'));
            })
            ->each(function (StoredWorkflowSignal $signal): void {
                $this->{$signal->method}(...($signal->arguments !== null ? Y::unserialize($signal->arguments) : []));
            });

        if ($parentWorkflow !== null) {
            $this->now = Carbon::parse($parentWorkflow->parents_pivot->parent_now);
        } else {
            $this->now = $log !== null ? $log->now : Carbon::now();
        }

        WorkflowStub::setContext([
            'storedWorkflow' => $this->storedWorkflow,
            'index' => $this->index,
            'now' => $this->now,
            'replaying' => $this->replaying,
        ]);

        $this->coroutine = $this->execute(...$this->resolveClassMethodDependencies(
            $this->arguments,
            $this,
            'execute'
        ));

        while ($this->coroutine->valid()) {
            $context = WorkflowStub::getContext();
            if ($context === null) {
                throw new \RuntimeException('The context must be set.');
            }

            $this->index = $context->index;

            $nextLog = $this->storedWorkflow->logs()
                ->whereIndex($this->index)
                ->first();

            if ($log !== null) {
                if ($log->created_at === null) {
                    throw new \RuntimeException('The Log must have a created_at timestamp.');
                }

                $this->storedWorkflow
                    ->signals()
                    ->where('created_at', '>', $log->created_at->format('Y-m-d H:i:s.u'))
                    ->when($nextLog, static function ($query, $nextLog): void {
                        if ($nextLog->created_at === null) {
                            throw new \RuntimeException('The Log must have a created_at timestamp.');
                        }
                        $query->where('created_at', '<=', $nextLog->created_at->format('Y-m-d H:i:s.u'));
                    })
                    ->each(function ($signal): void {
                        $this->{$signal->method}(...Y::unserialize($signal->arguments));
                    });
            }

            $log = $nextLog;

            $this->now = $log !== null ? $log->now : Carbon::now();

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

            $this->storedWorkflow->output = Y::serialize($return);

            $this->storedWorkflow->status->transitionTo(WorkflowCompletedStatus::class);

            $encodedReturn = json_encode($return);
            if ($encodedReturn === false) {
                throw new Exception('Could not encode return.');
            }

            WorkflowCompleted::dispatch(
                $this->storedWorkflow->id,
                $encodedReturn,
                now()
                    ->format('Y-m-d\TH:i:s.u\Z')
            );

            if ($parentWorkflow !== null) {
                $parentWorkflow->toWorkflow()
                    ->next(
                        $parentWorkflow->parents_pivot->parent_index,
                        $this->now,
                        $this->storedWorkflow->class,
                        $return
                    );
            }
        }
    }
}
