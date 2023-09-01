<?php

declare(strict_types=1);

namespace Workflow;

use BadMethodCallException;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use React\Promise\PromiseInterface;
use Throwable;
use Workflow\Middleware\WithoutOverlappingMiddleware;
use Workflow\Models\StoredWorkflow;
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
    use Sagas;
    use SerializesModels;

    public int $tries = 0;

    public int $maxExceptions = 0;

    public $arguments;

    public $coroutine;

    public int $index = 0;

    public $now;

    public bool $replaying = false;

    public function __construct(
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
            ->first();

        if ($parentWorkflow) {
            return [new WithoutOverlappingMiddleware($parentWorkflow->id, WithoutOverlappingMiddleware::ACTIVITY)];
        }
        return [
            new WithoutOverlappingMiddleware($this->storedWorkflow->id, WithoutOverlappingMiddleware::WORKFLOW),
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
            ->first();

        $log = $this->storedWorkflow->logs()
            ->whereIndex($this->index)
            ->first();

        $this->storedWorkflow
            ->signals()
            ->when($log, static function ($query, $log): void {
                $query->where('created_at', '<=', $log->created_at->format('Y-m-d H:i:s.u'));
            })
            ->each(function ($signal): void {
                $this->{$signal->method}(...Y::unserialize($signal->arguments));
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

        $this->coroutine = $this->{'execute'}(...$this->arguments);

        while ($this->coroutine->valid()) {
            $this->index = WorkflowStub::getContext()->index;

            $nextLog = $this->storedWorkflow->logs()
                ->whereIndex($this->index)
                ->first();

            if ($log) {
                $this->storedWorkflow
                    ->signals()
                    ->where('created_at', '>', $log->created_at->format('Y-m-d H:i:s.u'))
                    ->when($nextLog, static function ($query, $nextLog): void {
                        $query->where('created_at', '<=', $nextLog->created_at->format('Y-m-d H:i:s.u'));
                    })
                    ->each(function ($signal): void {
                        $this->{$signal->method}(...Y::unserialize($signal->arguments));
                    });
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
                throw new Exception('Workflow failed.');
            }

            $this->storedWorkflow->output = Y::serialize($return);

            $this->storedWorkflow->status->transitionTo(WorkflowCompletedStatus::class);

            if ($parentWorkflow) {
                $parentWorkflow->toWorkflow()
                    ->next($parentWorkflow->pivot->parent_index, $this->now, $this->storedWorkflow->class, $return);
            }
        }
    }
}
