<?php

declare(strict_types=1);

namespace Workflow\Middleware;

use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\InteractsWithTime;

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

    private $job;

    private $active = true;

    private $timeoutKey;

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
        $locked = $this->lock();

        if ($locked) {
            Queue::before(
                fn (JobProcessing $event) => $this->active = $job->job->getJobId() === $event->job->getJobId()
            );
            Queue::stopping(fn () => $this->active ? $this->unlock() : null);
            try {
                $next($job);
            } finally {
                $this->unlock();
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

    public function lock()
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
                    $this->timeoutKey = $this->getActivitySemaphoreKey() . bin2hex(random_bytes(16));
                    $locked = $this->compareAndSet(
                        $this->getActivitySemaphoreKey(),
                        $activitySemaphores,
                        array_merge($activitySemaphores, [$this->timeoutKey])
                    );
                    if ($locked) {
                        $this->cache->put($this->timeoutKey, $this->expiresAfter);
                    }
                }
                break;

            default:
                $locked = false;
                break;
        }

        return $locked;
    }

    public function unlock()
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
                        array_diff($activitySemaphore, [$this->timeoutKey])
                    );
                    if ($unlocked) {
                        $this->cache->forget($this->timeoutKey);
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
                    $this->cache->put($key, $newValue, $expiresAfter);
                    return true;
                }
            } finally {
                $lock->release();
            }
        }

        return false;
    }
}
