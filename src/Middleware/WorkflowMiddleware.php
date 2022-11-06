<?php

declare(strict_types=1);

namespace Workflow\Middleware;

final class WorkflowMiddleware
{
    public function handle($job, $next): void
    {
        $result = $next($job);

        try {
            $job->storedWorkflow->toWorkflow()
                ->next($job->index, $result);
        } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
            if ($job->storedWorkflow->toWorkflow()->running()) {
                $job->release();
            }
        }
    }
}
