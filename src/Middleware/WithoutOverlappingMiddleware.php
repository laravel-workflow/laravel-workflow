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
        $activitySemaphore = (int) $this->cache->get($this->getActivitySemaphoreKey(), 0);

        switch ($this->type) {
            case self::WORKFLOW:
                $locked = false;
                if ($workflowSemaphore === 0 && $activitySemaphore === 0) {
                    $locked = $this->compareAndSet(
                        $this->getWorkflowSemaphoreKey(),
                        $workflowSemaphore,
                        $workflowSemaphore + 1
                    );
                }
                break;

            case self::ACTIVITY:
                $locked = false;
                if ($workflowSemaphore === 0) {
                    $locked = $this->compareAndSet(
                        $this->getActivitySemaphoreKey(),
                        $activitySemaphore,
                        $activitySemaphore + 1
                    );
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
                    $workflowSemaphore = (int) $this->cache->get($this->getWorkflowSemaphoreKey());
                    $unlocked = $this->compareAndSet(
                        $this->getWorkflowSemaphoreKey(),
                        $workflowSemaphore,
                        max($workflowSemaphore - 1, 0)
                    );
                }
                break;

            case self::ACTIVITY:
                $unlocked = false;
                while (! $unlocked) {
                    $activitySemaphore = (int) $this->cache->get($this->getActivitySemaphoreKey());
                    $unlocked = $this->compareAndSet(
                        $this->getActivitySemaphoreKey(),
                        $activitySemaphore,
                        max($activitySemaphore - 1, 0)
                    );
                }
                break;
        }
    }

    private function compareAndSet($key, $expectedValue, $newValue)
    {
        $lock = $this->cache->lock($this->getLockKey(), $this->expiresAfter);

        if ($lock->get()) {
            try {
                $currentValue = (int) $this->cache->get($key, null);

                if ($currentValue === $expectedValue) {
                    $this->cache->put($key, (int) $newValue);
                    return true;
                }
            } finally {
                $lock->release();
            }
        }

        return false;
    }
}
