<?php

declare(strict_types=1);

namespace Workflow\Middleware;

use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as Cache;
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

    public function __construct($workflowId, $type, $releaseAfter = 0, $expiresAfter = 0)
    {
        $this->key = "{$workflowId}";
        $this->type = $type;
        $this->releaseAfter = $releaseAfter;
        $this->expiresAfter = $this->secondsUntil($expiresAfter);
    }

    public function handle($job, $next)
    {
        $cache = Container::getInstance()->make(Cache::class);
        $workflowSemaphore = (int) $cache->get($this->getWorkflowSemaphoreKey(), 0);
        $activitySemaphore = (int) $cache->get($this->getActivitySemaphoreKey(), 0);

        switch ($this->type) {
            case self::WORKFLOW:
                $locked = false;
                if ($workflowSemaphore === 0 && $activitySemaphore === 0) {
                    $locked = $this->compareAndSet(
                        $cache,
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
                        $cache,
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

        if ($locked) {
            try {
                $next($job);
            } finally {
                switch ($this->type) {
                    case self::WORKFLOW:
                        $unlocked = false;
                        while (! $unlocked) {
                            $workflowSemaphore = (int) $cache->get($this->getWorkflowSemaphoreKey());
                            $unlocked = $this->compareAndSet(
                                $cache,
                                $this->getWorkflowSemaphoreKey(),
                                $workflowSemaphore,
                                max($workflowSemaphore - 1, 0)
                            );
                        }
                        break;

                    case self::ACTIVITY:
                        $unlocked = false;
                        while (! $unlocked) {
                            $activitySemaphore = (int) $cache->get($this->getActivitySemaphoreKey());
                            $unlocked = $this->compareAndSet(
                                $cache,
                                $this->getActivitySemaphoreKey(),
                                $activitySemaphore,
                                max($activitySemaphore - 1, 0)
                            );
                        }
                        break;
                }
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

    private function compareAndSet($cache, $key, $expectedValue, $newValue)
    {
        $lock = $cache->lock($this->getLockKey(), $this->expiresAfter);

        if ($lock->get()) {
            try {
                $currentValue = (int) $cache->get($key, null);

                if ($currentValue === $expectedValue) {
                    $cache->put($key, (int) $newValue);
                    return true;
                }
            } finally {
                $lock->release();
            }
        }

        return false;
    }
}
