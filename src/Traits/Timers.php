<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Carbon\CarbonInterval;
use Illuminate\Database\QueryException;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function PHPStan\dumpType;
use function React\Promise\resolve;
use Workflow\Serializers\Y;
use Workflow\Signal;

trait Timers
{
    /**
     * @return PromiseInterface<bool>
     */
    public static function timer(string|int $seconds): PromiseInterface
    {
        if (self::$context === null) {
            throw new \RuntimeException('ActivityStub::timer() must be called within a workflow');
        }

        if (is_string($seconds)) {
            $seconds = (int) CarbonInterval::fromString($seconds)->totalSeconds;
        }

        if ($seconds <= 0) {
            ++self::$context->index;
            return resolve(true);
        }

        $log = self::$context->storedWorkflow->logs()
            ->whereIndex(self::$context->index)
            ->first();

        if ($log !== null) {
            ++self::$context->index;
            return resolve($log->result !== null ? Y::unserialize($log->result) : null);
        }

        $timer = self::$context->storedWorkflow->timers()
            ->whereIndex(self::$context->index)
            ->first();

        if ($timer === null) {
            $when = self::$context->now->copy()
                ->addSeconds($seconds);

            if (! self::$context->replaying) {
                $timer = self::$context->storedWorkflow->timers()
                    ->create([
                        'index' => self::$context->index,
                        'stop_at' => $when,
                    ]);
            }
        }

        if ($timer === null) {
            throw new \RuntimeException('A timer must have been created, but it was not found.');
        }

        $result = $timer->stop_at
            ->lessThanOrEqualTo(self::$context->now);

        if ($result === true) {
            if (! self::$context->replaying) {
                try {
                    self::$context->storedWorkflow->logs()
                        ->create([
                            'index' => self::$context->index,
                            'now' => self::$context->now,
                            'class' => Signal::class,
                            'result' => Y::serialize(true),
                        ]);
                } catch (QueryException $exception) {
                    // already logged
                }
            }
            ++self::$context->index;
            return resolve(true);
        }

        if (! self::$context->replaying) {
            Signal::dispatch(self::$context->storedWorkflow, self::connection(), self::queue())->delay($timer->stop_at);
        }

        ++self::$context->index;
        $deferred = new Deferred();
        return $deferred->promise();
    }
}
