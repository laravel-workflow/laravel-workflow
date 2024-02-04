<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Illuminate\Database\QueryException;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use Workflow\Serializers\Y;
use Workflow\Signal;

trait Awaits
{
    /**
     * @param callable():bool $condition
     * @return PromiseInterface<bool>
     */
    public static function await(callable $condition): PromiseInterface
    {
        if (self::$context === null) {
            throw new \RuntimeException('ActivityStub::await() must be called within a workflow');
        }

        $log = self::$context->storedWorkflow->logs()
            ->whereIndex(self::$context->index)
            ->first();

        if ($log !== null) {
            ++self::$context->index;
            return resolve($log->result !== null ? Y::unserialize($log->result) : null);
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

                    if ($log !== null) {
                        ++self::$context->index;
                        return resolve($log->result !== null ? Y::unserialize($log->result) : null);
                    }
                }
            }
            ++self::$context->index;
            return resolve($result);
        }

        ++self::$context->index;
        $deferred = new Deferred();
        return $deferred->promise();
    }
}
