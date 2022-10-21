<?php

namespace Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use React\Promise\PromiseInterface;
use Throwable;
use Workflow\Models\StoredWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowRunningStatus;
use Workflow\States\WorkflowWaitingStatus;

abstract class Workflow implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $maxExceptions = 3;

    public $arguments;

    public $coroutine;

    public $index;

    public $model;

    abstract public function execute();

    public function __construct(StoredWorkflow $model, ...$arguments)
    {
        $this->model = $model;
        $this->arguments = $arguments;
        $this->index = 0;
    }

    public function failed(Throwable $exception)
    {
        $this->model->toWorkflow()->fail($this->index, $exception);
    }

    public function handle()
    {
        $this->model->status->transitionTo(WorkflowRunningStatus::class);

        $this->coroutine = $this->execute(...$this->arguments);

        while ($this->coroutine->valid()) {
            $log = $this->model->logs()->whereIndex($this->index)->first();

            if ($log) {
                $previousLog = $this->model->logs()->whereIndex($this->index - 1)->first();

                $this->model
                    ->signals()
                    ->where('created_at', '<=', $log->created_at)
                    ->when($previousLog, function($query, $previousLog) {
                        $query->where('created_at', '>', $previousLog);
                    })
                    ->each(function ($signal) {
                        $this->{$signal->method}(...unserialize($signal->arguments));
                    });
            }

            $index = $this->index++;

            $current = $this->coroutine->current();

            if ($current instanceof PromiseInterface) {
                $resolved = false;

                $current->then(function ($value) use ($index, &$resolved) {
                    $resolved = true;

                    $this->model->logs()->create([
                        'index' => $index,
                        'result' => serialize(true),
                    ]);

                    $log = $this->model->logs()->whereIndex($index)->first();

                    $this->coroutine->send(unserialize($log->result));
                });

                if (!$resolved) {
                    $this->model->status->transitionTo(WorkflowWaitingStatus::class);

                    return;
                }
            } else {
                $log = $this->model->logs()->whereIndex($index)->first();

                if ($log) {
                    $nextLog = $this->model->logs()->whereIndex($index + 1)->first();

                    $this->model
                        ->signals()
                        ->where('created_at', '>=', $log->created_at)
                        ->when($nextLog, function($query, $nextLog) {
                            $query->where('created_at', '<', $nextLog);
                        })
                        ->each(function ($signal) {
                            $this->{$signal->method}(...unserialize($signal->arguments));
                        });

                    $this->coroutine->send(unserialize($log->result));
                } else {
                    $this->model->status->transitionTo(WorkflowWaitingStatus::class);

                    $current->activity()::dispatch($index, $this->model, ...$current->arguments());

                    return;
                }
            }
        }

        $this->model->output = serialize($this->coroutine->getReturn());

        $this->model->status->transitionTo(WorkflowCompletedStatus::class);
    }
}
