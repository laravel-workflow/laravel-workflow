<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Carbon\CarbonInterval;
use Illuminate\Database\QueryException;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use Workflow\Serializers\Serializer;
use Workflow\Signal;

trait AwaitWithTimeouts
{
    public static function awaitWithTimeout(int|string|CarbonInterval $seconds, $condition): PromiseInterface
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

                    // Handle duplicate key exceptions from both SQL (QueryException) and MongoDB (BulkWriteException)
                    $isDuplicateKey = $exception instanceof QueryException || 
                                     str_contains(get_class($exception), 'BulkWriteException') ||
                                     str_contains($exception->getMessage(), 'duplicate key') ||
                                     str_contains($exception->getMessage(), 'E11000');
                    
                    if (!$isDuplicateKey) {
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

        return self::timer($seconds)->then(static fn ($completed): bool => ! $completed);
    }
}
