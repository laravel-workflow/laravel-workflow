<?php

declare(strict_types=1);

namespace Workflow\Middleware;

use Exception;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Workflow\Serializers\Y;

final class WorkflowMiddleware
{
    private $active = true;

    public function handle($job, $next): void
    {
        Queue::before(fn (JobProcessing $event) => $this->active = $job->job->getJobId() === $event->job->getJobId());
        Queue::stopping(fn () => $this->active ? $job->storedWorkflow->exceptions()
            ->create([
                'class' => $job::class,
                'exception' => Y::serialize(new Exception('Activity timed out.')),
            ]) : null);

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
}
