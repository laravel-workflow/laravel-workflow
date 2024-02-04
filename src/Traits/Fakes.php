<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use function PHPStan\dumpType;

/**
 * @method static void mock(string $class, mixed $result)
 * @method static array<string, mixed[]> mocks()
 * @method static void assertNothingDispatched()
 * @method static void assertDispatched(string $workflowOrActivity, callable|int|null $callback = null)
 * @method static void assertNotDispatched(string $workflowOrActivity, callable|null $callback = null)
 * @method static void assertDispatchedTimes(string $workflowOrActivity, int $times = 1)
 * @method static void recordDispatched(string $class, mixed $arguments)
 * @method static Collection<int, mixed[]> dispatched(string $workflowOrActivity, callable|null $callback = null)
 */
trait Fakes
{
    protected static string $DISPATCHED_LIST = 'workflow.dispatched';

    protected static string $MOCKS_LIST = 'workflow.mocks';

    public static function faked(): bool
    {
        return App::bound(static::$MOCKS_LIST);
    }

    public static function fake(): void
    {
        App::bind(static::$MOCKS_LIST, static function () {
            return [];
        });

        App::bind(static::$DISPATCHED_LIST, static function () {
            return [];
        });

        static::macro('mocks', static function () {
            return App::make(static::$MOCKS_LIST);
        });

        static::macro('mock', static function (string $class, mixed $result) {
            $mocks = static::mocks();

            App::bind(static::$MOCKS_LIST, static function () use ($mocks, $class, $result) {
                $mocks[$class] = $result;
                return $mocks;
            });
        });

        static::macro('recordDispatched', static function (string $class, mixed $arguments) {
            $dispatched = App::make(static::$DISPATCHED_LIST);

            App::bind(static::$DISPATCHED_LIST, static function () use ($dispatched, $class, $arguments) {
                if (! isset($dispatched[$class])) {
                    $dispatched[$class] = [];
                }

                $dispatched[$class][] = $arguments;

                return $dispatched;
            });
        });

        static::macro('assertDispatched', static function (string $workflowOrActivity, callable|int|null $callback = null) {
            if (is_int($callback)) {
                self::assertDispatchedTimes($workflowOrActivity, $callback);
                return;
            }

            \PHPUnit\Framework\Assert::assertTrue(
                self::dispatched($workflowOrActivity, $callback)->count() > 0,
                "The expected [{$workflowOrActivity}] workflow/activity was not dispatched."
            );
        });

        static::macro('assertDispatchedTimes', static function (string $workflowOrActivity, int $times = 1) {
            $count = self::dispatched($workflowOrActivity)->count();

            \PHPUnit\Framework\Assert::assertSame(
                $times,
                $count,
                "The expected [{$workflowOrActivity}] workflow/activity was dispatched {$count} times instead of {$times} times."
            );
        });

        static::macro('assertNotDispatched', static function (string $workflowOrActivity, callable|null $callback = null) {
            \PHPUnit\Framework\Assert::assertTrue(
                self::dispatched($workflowOrActivity, $callback)->count() === 0,
                "The unexpected [{$workflowOrActivity}] workflow/activity was dispatched."
            );
        });

        static::macro('assertNothingDispatched', static function () {
            $dispatched = App::make(self::$DISPATCHED_LIST);
            \PHPUnit\Framework\Assert::assertTrue(
                count($dispatched) === 0,
                'An unexpected workflow/activity was dispatched.'
            );
        });

        static::macro('dispatched', static function (string $workflowOrActivity, callable|null $callback = null): Collection {
            /** @var Collection<string, mixed[]> $dispatched */
            $dispatched = App::make(self::$DISPATCHED_LIST);

            if (! isset($dispatched[$workflowOrActivity])) {
                return collect();
            }

            $callback = $callback ?? static fn () => true;

            return collect($dispatched[$workflowOrActivity])->filter(
                static fn ($arguments) => $callback(...$arguments)
            );
        });
    }
}
