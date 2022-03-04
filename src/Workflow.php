<?php

namespace Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Workflow\Exceptions\WorkflowFailedException;
use Workflow\Models\StoredWorkflow;
use Workflow\States\WorkflowCompletedStatus;
use Workflow\States\WorkflowFailedStatus;
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
        $this->model->log = is_null($this->model->log) ? collect() : $this->model->log;

        $this->model->status->transitionTo(WorkflowRunningStatus::class);

        $this->coroutine = $this->execute(...$this->arguments);

        while ($this->coroutine->valid()) {
            $index = $this->index++;

            $current = $this->coroutine->current();

            if ($this->model->log->has($index)) {
                $this->coroutine->send(unserialize($this->model->log->get($index)));
            } else {
                $this->model->status->transitionTo(WorkflowWaitingStatus::class);
                $current->activity()::dispatch($index, $this->model->fresh(), ...$current->arguments());
                return;
            }
        }

        $this->model->output = serialize($this->coroutine->getReturn());

        $this->model->status->transitionTo(WorkflowCompletedStatus::class);
    }
}
