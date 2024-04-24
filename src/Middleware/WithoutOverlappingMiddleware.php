<?php

declare(strict_types=1);

namespace Workflow\Middleware;

use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\InteractsWithTime;
use Illuminate\Support\Str;

class WithoutOverlappingMiddleware
{
    use InteractsWithTime;

    public const WORKFLOW = 1;

    public const ACTIVITY = 2;

    public string $key;

    public int $type;

    public $releaseAfter;

    public $expiresAfter;

    public $prefix = 'laravel-workflow-overlap:';

    private $cache;

    public function __construct($workflowId, $type, $releaseAfter = 0, $expiresAfter = 0)
    {
        $this->key = "{$workflowId}";
        $this->type = $type;
        $this->releaseAfter = $releaseAfter;
        $this->expiresAfter = $this->secondsUntil($expiresAfter);
        $this->cache = Container::getInstance()->make(Cache::class);
    }

    public function handle($job, $next)
    {
        $locked = $this->lock($job);

        if ($locked) {
            try {
                $next($job);
            } finally {
                $this->unlock($job);
            }
        } elseif ($this->releaseAfter !== null) {
            $job->release($this->releaseAfter);
        }
    }

    public function getLockKey()
    {
        return $this->prefix . $this->key;
    }

    public function getWorkflowSemaphoreKey()
    {
        return $this->getLockKey() . ':workflow';
    }

    public function getActivitySemaphoreKey()
    {
        return $this->getLockKey() . ':activity';
    }

    public function lock($job)
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
                        (int) $workflowSemaphore,
                        (int) $workflowSemaphore + 1,
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
                        if ($this->expiresAfter) {
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

    public function unlock($job)
    {
        switch ($this->type) {
            case self::WORKFLOW:
                $unlocked = false;
                while (! $unlocked) {
                    $workflowSemaphore = (int) $this->cache->get($this->getWorkflowSemaphoreKey(), 0);
                    $unlocked = $this->compareAndSet(
                        $this->getWorkflowSemaphoreKey(),
                        (int) $workflowSemaphore,
                        (int) max($workflowSemaphore - 1, 0),
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

    private function compareAndSet($key, $expectedValue, $newValue, $expiresAfter = 0)
    {
        $lock = $this->cache->lock($this->getLockKey());

        if ($lock->get()) {
            try {
                $currentValue = $this->cache->get($key, $expectedValue);

                $currentValue = is_int($expectedValue) ? (int) $currentValue : $currentValue;

                if ($currentValue === $expectedValue) {
                    if ($expiresAfter) {
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
