<?php

namespace Workflow;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Workflow\Models\StoredWorkflow;

class Signal implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public $tries = 3;

    public $maxExceptions = 3;

    public $model;

    public function __construct(StoredWorkflow $model)
    {
        $this->model = $model;
    }

    public function handle()
    {
        $workflow = $this->model->toWorkflow();

        if ($workflow->running()) {
            try {
                $workflow->resume();
            } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
                $this->release();
            }
        }
    }
}
