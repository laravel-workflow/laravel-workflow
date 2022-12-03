<?php

declare(strict_types=1);

namespace Workflow;

use BadMethodCallException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use React\Promise\PromiseInterface;
use Throwable;
use Workflow\Models\StoredWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowRunningStatus;
use Workflow\States\WorkflowWaitingStatus;

class Workflow implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 0;

    public int $maxExceptions = 0;

    public $arguments;

    public $coroutine;

    public int $index = 0;

    public $now;

    public function __construct(
        public StoredWorkflow $storedWorkflow,
        ...$arguments
    ) {
        $this->arguments = $arguments;
    }

    public function middleware()
    {
        return [
            (new WithoutOverlapping("workflow:{$this->storedWorkflow->id}"))->shared(),
        ];
    }

    public function failed(Throwable $throwable): void
    {
        try {
            $this->storedWorkflow->toWorkflow()
                ->fail($this->index, $throwable);
        } catch (\Throwable) {
        }
    }

    public function handle(): void
    {
        if (! method_exists($this, 'execute')) {
            throw new BadMethodCallException('Execute method not implemented.');
        }

        try {
            $this->storedWorkflow->status->transitionTo(WorkflowRunningStatus::class);
        } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
            if ($this->storedWorkflow->toWorkflow()->running()) {
                $this->release();
            }
            return;
        }

        $log = $this->storedWorkflow->logs()
            ->whereIndex($this->index)
            ->first();

        $this->storedWorkflow
            ->signals()
            ->when($log, static function ($query, $log): void {
                $query->where('created_at', '<=', $log->created_at->format('Y-m-d H:i:s.u'));
            })
            ->each(function ($signal): void {
                $this->{$signal->method}(...unserialize($signal->arguments));
            });

        $this->now = $log ? $log->now : Carbon::now();

        WorkflowStub::setContext([
            'storedWorkflow' => $this->storedWorkflow,
            'index' => $this->index,
            'now' => $this->now,
        ]);

        $this->coroutine = $this->{'execute'}(...$this->arguments);

        while ($this->coroutine->valid()) {
            $nextLog = $this->storedWorkflow->logs()
                ->whereIndex($this->index + 1)
                ->first();

            $this->storedWorkflow
                ->signals()
                ->when($nextLog, static function ($query, $nextLog): void {
                    $query->where('created_at', '<=', $nextLog->created_at->format('Y-m-d H:i:s.u'));
                })
                ->when($log, static function ($query, $log): void {
                    $query->where('created_at', '>', $log->created_at->format('Y-m-d H:i:s.u'));
                })
                ->each(function ($signal): void {
                    $this->{$signal->method}(...unserialize($signal->arguments));
                });

            $this->now = $nextLog ? $nextLog->now : Carbon::now();

            WorkflowStub::setContext([
                'storedWorkflow' => $this->storedWorkflow,
                'index' => $this->index,
                'now' => $this->now,
            ]);

            $current = $this->coroutine->current();

            if ($current instanceof PromiseInterface) {
                $resolved = false;

                $current->then(function ($value) use (&$resolved): void {
                    $resolved = true;

                    $log = $this->storedWorkflow->logs()
                        ->whereIndex($this->index)
                        ->first();

                    if (! $log) {
                        $log = $this->storedWorkflow->logs()
                            ->create([
                                'index' => $this->index,
                                'now' => $this->now,
                                'class' => Signal::class,
                                'result' => serialize($value),
                            ]);
                    }

                    $this->coroutine->send(unserialize($log->result));
                });

                if (! $resolved) {
                    $this->storedWorkflow->status->transitionTo(WorkflowWaitingStatus::class);

                    return;
                }
            } else {
                $log = $this->storedWorkflow->logs()
                    ->whereIndex($this->index)
                    ->first();

                if ($log) {
                    $this->coroutine->send(unserialize($log->result));
                } else {
                    $this->storedWorkflow->status->transitionTo(WorkflowWaitingStatus::class);

                    $current->activity()::dispatch(
                        $this->index,
                        $this->now,
                        $this->storedWorkflow,
                        ...$current->arguments()
                    );

                    return;
                }
            }

            ++$this->index;
        }

        $this->storedWorkflow->status->transitionTo(WorkflowCompletedStatus::class);

        $this->storedWorkflow->output = serialize($this->coroutine->getReturn());
    }
}
