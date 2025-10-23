<?php

declare(strict_types=1);

namespace Workflow\Traits;

use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use Workflow\Domain\Contracts\ExceptionHandlerInterface;
use Workflow\Serializers\Serializer;
use Workflow\Signal;

trait Awaits
{
    public static function await($condition): PromiseInterface
    {
        $log = self::$context->storedWorkflow->logs()
            ->whereIndex(self::$context->index)
            ->first();

        if ($log) {
            ++self::$context->index;
            return resolve(Serializer::unserialize($log->result));
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
                            'result' => Serializer::serialize($result),
                        ]);
                } catch (\Throwable $exception) {
                    $exceptionHandler = app(ExceptionHandlerInterface::class);

                    if (! $exceptionHandler->isDuplicateKeyException($exception)) {
                        throw $exception;
                    }

                    $log = self::$context->storedWorkflow->logs()
                        ->whereIndex(self::$context->index)
                        ->first();

                    if ($log) {
                        ++self::$context->index;
                        return resolve(Serializer::unserialize($log->result));
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
