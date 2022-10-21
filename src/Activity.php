<?php

namespace Workflow;

use BadMethodCallException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Workflow\Middleware\WorkflowMiddleware;
use Workflow\Models\StoredWorkflow;

class Activity implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $tries = 3;

    public $maxExceptions = 3;

    public $arguments;

    public $index;

    public $model;

    public function __construct(int $index, StoredWorkflow $model, ...$arguments)
    {
        $this->index = $index;
        $this->model = $model;
        $this->arguments = $arguments;
    }

    public function handle()
    {
        if (! method_exists($this, 'execute')) {
            throw new BadMethodCallException('Execute method mot implemented.');
        }

        return $this->{'execute'}(...$this->arguments);
    }

    public function middleware()
    {
        return [new WorkflowMiddleware()];
    }

    public function failed(Throwable $exception)
    {
        $this->model->toWorkflow()->fail($exception);
    }
}
