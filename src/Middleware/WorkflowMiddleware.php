<?php

declare(strict_types=1);

namespace Workflow\Middleware;

use Exception;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use LimitIterator;
use SplFileObject;
use Workflow\Events\ActivityCompleted;
use Workflow\Events\ActivityFailed;
use Workflow\Events\ActivityStarted;
use Workflow\Serializers\Y;

final class WorkflowMiddleware
{
    private $active = true;

    public function handle($job, $next): void
    {
        Queue::stopping(fn () => $this->active ? $job->storedWorkflow->exceptions()
            ->create([
                'class' => $job::class,
                'exception' => Y::serialize(new Exception('Activity timed out.')),
            ]) : null);

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
        } finally {
            $this->active = false;
        }
    }
}
