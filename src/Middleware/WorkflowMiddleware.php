<?php

declare(strict_types=1);

namespace Workflow\Middleware;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Workflow\Exception;

final class WorkflowMiddleware
{
    private $job;

    private $active = true;

    public function handle($job, $next): void
    {
        $this->job = $job;

        Queue::before(fn (JobProcessing $event) => $this->active = $job->job->getJobId() === $event->job->getJobId());
        Queue::stopping(fn () => $this->active ? $this->throwExceptionIfActive() : null);

        $result = $next($job);

        try {
            $job->storedWorkflow->toWorkflow()
                ->next($job->index, $job->now, $job::class, $result);
        } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
            if ($job->storedWorkflow->toWorkflow()->running()) {
                $job->release();
            }
        }
    }

    public function throwExceptionIfActive()
    {
        $workflow = $this->job->storedWorkflow->toWorkflow();

        Exception::dispatch(
            $this->job->index,
            $this->job->now,
            $this->job->storedWorkflow,
            new \Exception('Activity timed out.'),
            $workflow->connection(),
            $workflow->queue()
        );
    }
}
