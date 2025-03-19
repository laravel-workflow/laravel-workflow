<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Carbon\CarbonInterval;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use Workflow\Serializers\Serializer;
use Workflow\Signal;

trait Timers
{
    public static function timer($seconds): PromiseInterface
    {
        if (is_string($seconds)) {
            $seconds = CarbonInterval::fromString($seconds)->totalSeconds;
        }

        if ($seconds <= 0) {
            ++self::$context->index;
            return resolve(true);
        }

        $log = self::$context->storedWorkflow->logs()
            ->whereIndex(self::$context->index)
            ->first();

        if ($log) {
            ++self::$context->index;
            return resolve(Serializer::unserialize($log->result));
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
                            'result' => Serializer::serialize(true),
                        ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
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
