<?php

declare(strict_types=1);

namespace Workflow\Middleware;

use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Support\Str;
use Psr\SimpleCache\InvalidArgumentException;
use Workflow\Activity;
use Workflow\Models\StoredWorkflow;
use Workflow\Workflow;

class WithoutOverlappingMiddleware
{
    use InteractsWithTime;

    public const WORKFLOW = 1;

    public const ACTIVITY = 2;

    public string $key;

    /**
     * @var self::WORKFLOW|self::ACTIVITY
     */
    public int $type;

    public int $releaseAfter;

    public int $expiresAfter;

    public string $prefix = 'laravel-workflow-overlap:';

    /**
     * @var Cache&LockProvider
     */
    private $cache;

    private bool $active = true;

    /**
     * @param scalar $workflowId
     * @param self::WORKFLOW|self::ACTIVITY $type
     * @param int $releaseAfter
     * @param int $expiresAfter
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function __construct($workflowId, $type, int $releaseAfter = 0, int $expiresAfter = 0)
    {
        $this->key = "{$workflowId}";
        $this->type = $type;
        $this->releaseAfter = $releaseAfter;
        $this->expiresAfter = $this->secondsUntil($expiresAfter);
        // @phpstan-ignore-next-line
        $this->cache = Container::getInstance()->make(Cache::class);
    }

    /**
     * @param Workflow | Activity<Workflow, mixed> $job
     * @param callable $next
     * @return void
     */
    public function handle($job, $next) : void
    {
        $locked = $this->lock($job);

        if ($locked) {
            Queue::before(
                fn (JobProcessing $event) => $this->active = $job->job->getJobId() === $event->job->getJobId()
            );
            Queue::stopping(fn () => $this->active ? $this->unlock($job) : null);
            try {
                $next($job);
            } finally {
                $this->unlock($job);
            }
        } elseif ($this->releaseAfter !== null) {
            $job->release($this->releaseAfter);
        }
    }

    public function getLockKey(): string
    {
        return $this->prefix . $this->key;
    }

    public function getWorkflowSemaphoreKey(): string
    {
        return $this->getLockKey() . ':workflow';
    }

    public function getActivitySemaphoreKey(): string
    {
        return $this->getLockKey() . ':activity';
    }

    /**
     * @param Workflow | Activity<Workflow, mixed> $job
     * @return bool
     * @throws InvalidArgumentException
     */
    public function lock($job): bool
    {
        $workflowSemaphore = (int) $this->cache->get($this->getWorkflowSemaphoreKey(), 0);
        $activitySemaphore = 0;
        $activitySemaphores = $this->cache->get($this->getActivitySemaphoreKey(), []);

        foreach ($activitySemaphores as $semaphore) {
            if ($this->cache->has($semaphore)) {
                $activitySemaphore++;
            }
        }

        switch ($this->type) {
            case self::WORKFLOW:
                $locked = false;
                if ($workflowSemaphore === 0 && $activitySemaphore === 0) {
                    $locked = $this->compareAndSet(
                        $this->getWorkflowSemaphoreKey(),
                        $workflowSemaphore,
                        $workflowSemaphore + 1,
                        $this->expiresAfter
                    );
                }
                break;

            case self::ACTIVITY:
                $locked = false;
                if ($workflowSemaphore === 0) {
                    $job->key = $this->getActivitySemaphoreKey() . ':' . (string) Str::uuid();
                    $locked = $this->compareAndSet(
                        $this->getActivitySemaphoreKey(),
                        $activitySemaphores,
                        array_merge($activitySemaphores, [$job->key])
                    );
                    if ($locked) {
                        if ($this->expiresAfter > 0) {
                            $this->cache->put($job->key, 1, $this->expiresAfter);
                        } else {
                            $this->cache->put($job->key, 1);
                        }
                    }
                }
                break;

            default:
                $locked = false;
                break;
        }

        return $locked;
    }

    /**
     * @param Workflow | Activity<Workflow, mixed> $job
     * @return void
     * @throws InvalidArgumentException
     */
    public function unlock($job): void
    {
        switch ($this->type) {
            case self::WORKFLOW:
                $unlocked = false;
                while (! $unlocked) {
                    $workflowSemaphore = (int) $this->cache->get($this->getWorkflowSemaphoreKey(), 0);
                    $unlocked = $this->compareAndSet(
                        $this->getWorkflowSemaphoreKey(),
                        $workflowSemaphore,
                        max($workflowSemaphore - 1, 0),
                        $this->expiresAfter
                    );
                }
                break;

            case self::ACTIVITY:
                $unlocked = false;
                while (! $unlocked) {
                    $activitySemaphore = $this->cache->get($this->getActivitySemaphoreKey(), []);
                    $unlocked = $this->compareAndSet(
                        $this->getActivitySemaphoreKey(),
                        $activitySemaphore,
                        array_diff($activitySemaphore, [$job->key])
                    );
                    if ($unlocked) {
                        $this->cache->forget($job->key);
                    }
                }
                break;
        }
    }

    /**
     * @template T in int|string[]
     * @param string $key
     * @param T $expectedValue
     * @param T $newValue
     * @param int $expiresAfter
     * @return bool
     * @throws InvalidArgumentException
     */
    private function compareAndSet(string $key, $expectedValue, $newValue, int $expiresAfter = 0): bool
    {
        $lock = $this->cache->lock($this->getLockKey());

        if ($lock->get() === true) {
            try {
                $currentValue = $this->cache->get($key, $expectedValue);

                $currentValue = is_int($expectedValue) ? (int) $currentValue : $currentValue;

                if ($currentValue === $expectedValue) {
                    if ($expiresAfter > 0) {
                        $this->cache->put($key, $newValue, $expiresAfter);
                    } else {
                        $this->cache->put($key, $newValue);
                    }
                    return true;
                }
            } finally {
                $lock->release();
            }
        }

        return false;
    }
}
