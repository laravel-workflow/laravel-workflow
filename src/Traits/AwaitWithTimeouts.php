<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Carbon\CarbonInterval;
use Illuminate\Database\QueryException;
use React\Promise\PromiseInterface;
use function PHPStan\dumpType;
use function React\Promise\resolve;
use Workflow\Serializers\Y;
use Workflow\Signal;

trait AwaitWithTimeouts
{
    /**
     * @param int|string $seconds
     * @param callable():bool $condition
     * @return PromiseInterface<bool>
     */
    public static function awaitWithTimeout(int|string $seconds, callable $condition): PromiseInterface
    {
        $log = self::$context->storedWorkflow->logs()
            ->whereIndex(self::$context->index)
            ->first();

        if ($log) {
            ++self::$context->index;
            return resolve(Y::unserialize($log->result));
        }

        if (is_string($seconds)) {
            $seconds = (int) CarbonInterval::fromString($seconds)->totalSeconds;
        }

        $result = $condition();

        if ($result === true) {
            if (! self::$context->replaying) {
                try {
                    self::$context->storedWorkflow->logs()
                        ->create([
                            'index' => self::$context->index,
                            'now' => self::$context->now,
                            'class' => Signal::class,
                            'result' => Y::serialize($result),
                        ]);
                } catch (QueryException $exception) {
                    $log = self::$context->storedWorkflow->logs()
                        ->whereIndex(self::$context->index)
                        ->first();

                    if ($log) {
                        ++self::$context->index;
                        return resolve(Y::unserialize($log->result));
                    }
                }
            }
            ++self::$context->index;
            return resolve($result);
        }

        return self::timer($seconds)->then(static fn ($completed): bool => ! $completed);
    }
}
