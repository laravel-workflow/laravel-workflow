<?php

declare(strict_types=1);

namespace Workflow\Middleware;

use Illuminate\Support\Str;
use LimitIterator;
use SplFileObject;
use Workflow\Events\ActivityCompleted;
use Workflow\Events\ActivityFailed;
use Workflow\Events\ActivityStarted;

/**
 * Class ActivityMiddleware
 *
 * This middleware orchestrates the complete lifecycle of activity execution within a workflow.
 *
 * Execution Flow:
 * 1. Dispatch ActivityStarted event with activity details and unique UUID
 * 2. Allow the activity to execute ($next($job))
 * 3. Store the activity output/result in the database
 * 4. Attempt to update the workflow status to "pending" in preparation for continuation
 * 5. If status transition is valid: Dispatch the parent workflow back to the queue to continue execution
 * 6. If status transition fails (workflow already "running"): Release this activity back to the queue for retry
 * 7. Dispatch ActivityCompleted event
 *
 * Important: Due to the state transition logic in step 4-6, activities may be completed more than once
 * if there are timing conflicts with workflow state changes.
 *
 * On failure, it captures detailed exception information (including code snippets) and dispatches
 * ActivityFailed event before re-throwing the exception.
 *
 * This middleware acts as the bridge that allows activities to seamlessly hand their results back
 * to their parent workflow for continued execution, while managing state transitions safely.
 */
final class ActivityMiddleware
{
    public function handle($job, $next): void
    {
        $uuid = (string) Str::uuid();

        ActivityStarted::dispatch(
            $job->storedWorkflow->id,
            $uuid,
            $job::class,
            $job->index,
            json_encode($job->arguments),
            now()
                ->format('Y-m-d\TH:i:s.u\Z')
        );

        try {
            $result = $next($job);

            try {
                $job->storedWorkflow->toWorkflow()
                    ->next($job->index, $job->now, $job::class, $result);

                ActivityCompleted::dispatch(
                    $job->storedWorkflow->id,
                    $uuid,
                    json_encode($result),
                    now()
                        ->format('Y-m-d\TH:i:s.u\Z')
                );
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $throwable) {
                $job->storedWorkflow->toWorkflow()
                    ->fail($throwable);
            } catch (\Spatie\ModelStates\Exceptions\TransitionNotFound) {
                if ($job->storedWorkflow->toWorkflow()->running()) {
                    $job->release();
                }
            }
        } catch (\Throwable $throwable) {
            $file = new SplFileObject($throwable->getFile());
            $iterator = new LimitIterator($file, max(0, $throwable->getLine() - 4), 7);

            ActivityFailed::dispatch($job->storedWorkflow->id, $uuid, json_encode([
                'class' => get_class($throwable),
                'message' => $throwable->getMessage(),
                'code' => $throwable->getCode(),
                'line' => $throwable->getLine(),
                'file' => $throwable->getFile(),
                'trace' => $throwable->getTrace(),
                'snippet' => array_slice(iterator_to_array($iterator), 0, 7),
            ]), now()
                ->format('Y-m-d\TH:i:s.u\Z'));

            throw $throwable;
        }
    }
}
