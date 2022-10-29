<?php

namespace Workflow\Middleware;

class WorkflowMiddleware
{
    public function handle($job, $next)
    {
        $result = $next($job);

        try {
            $job->model->toWorkflow()->next($job->index, $result);
        } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
            if ($job->model->toWorkflow()->running()) {
                $job->release();
            }
        }
    }
}
