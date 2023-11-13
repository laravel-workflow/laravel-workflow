<?php
declare(strict_types=1);

namespace Workflow\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Assert as PHPUnit;

trait AssertDispatchedWorkflowsOrActivities
{
    public static function dispatchedWorkflowsOrActivities()
    {
        if (! static::faked()) {
            return [];
        }
        return App::make(static::DISPATCHED_WORKFLOWS_OR_ACTIVITIES_LIST);
    }

    public static function recordDispatchedWorkflowOrActivity($class, $arguments) : void
    {
        if (! static::faked()) {
            return;
        }

        $dispatchedWorkflowsOrActivities = static::dispatchedWorkflowsOrActivities();

        App::bind(static::DISPATCHED_WORKFLOWS_OR_ACTIVITIES_LIST, static function ($app) use ($dispatchedWorkflowsOrActivities, $class, $arguments) {
            if (! isset($dispatchedWorkflowsOrActivities[$class])) {
                $dispatchedWorkflowsOrActivities[$class] = [];
            }

            $dispatchedWorkflowsOrActivities[$class][] = $arguments;

            return $dispatchedWorkflowsOrActivities;
        });
    }

    /**
     * Assert if an activity was dispatched based on a truth-test callback.
     *
     * @param callable|int|null $callback
     */
    public static function assertDispatched(string $workflowOrActivity, $callback = null) : void
    {
        if (is_int($callback)) {
            self::assertDispatchedTimes($workflowOrActivity, $callback);
            return;
        }

        PHPUnit::assertTrue(
            self::dispatched($workflowOrActivity, $callback)->count() > 0,
            "The expected [{$workflowOrActivity}] workflow/activity was not dispatched."
        );
    }

    public static function assertDispatchedTimes(string $workflowOrActivity, int $times = 1)
    {
        $count = self::dispatched($workflowOrActivity)->count();

        PHPUnit::assertSame(
            $times, $count,
            "The expected [{$workflowOrActivity}] workflow/activity was dispatched {$count} times instead of {$times} times."
        );
    }

    /**
     * Get all of the activities matching a truth-test callback.
     *
     * @param  string  $workflowOrActivity
     * @param  callable|null  $callback
     * @return Collection
     */
    public static function dispatched(string $workflowOrActivity, $callback = null) : Collection
    {
        $dispatchedWorkflowsOrActivities = App::make(self::DISPATCHED_WORKFLOWS_OR_ACTIVITIES_LIST);
        if (! isset($dispatchedWorkflowsOrActivities[$workflowOrActivity])) {
            return collect();
        }

        $callback = $callback ?: fn () => true;

        return collect($dispatchedWorkflowsOrActivities[$workflowOrActivity])->filter(
            fn ($arguments) => $callback(...$arguments)
        );
    }
}
