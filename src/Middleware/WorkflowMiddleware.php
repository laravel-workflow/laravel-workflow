<?php

namespace Workflow\Middleware;

class WorkflowMiddleware
{
    public function handle($job, $next)
    {
        $result = $next($job);

        $job->model->toWorkflow()->next($job->index, $result);
    }
}
