<?php

declare(strict_types=1);

namespace Workflow\Infrastructure\Persistence\MongoDB;

use Workflow\Domain\Contracts\ExceptionHandlerInterface;

/**
 * MongoDB exception handler for MongoDB-specific exceptions.
 */
class MongoDBExceptionHandler implements ExceptionHandlerInterface
{
    public function isDuplicateKeyException(\Throwable $exception): bool
    {
        // MongoDB throws BulkWriteException for duplicate keys
        if (str_contains(get_class($exception), 'BulkWriteException')) {
            return true;
        }

        // Check for MongoDB duplicate key error codes and messages
        if (str_contains($exception->getMessage(), 'E11000') ||
            str_contains($exception->getMessage(), 'duplicate key')) {
            return true;
        }

        // Also check Laravel's UniqueConstraintViolationException in case it's wrapped
        if ($exception instanceof \Illuminate\Database\UniqueConstraintViolationException) {
            return true;
        }

        return false;
    }

    public function isConnectionException(\Throwable $exception): bool
    {
        // MongoDB connection exceptions
        if (str_contains(get_class($exception), 'MongoDB\\Driver\\Exception\\ConnectionException') ||
            str_contains(get_class($exception), 'MongoDB\\Driver\\Exception\\ConnectionTimeoutException')) {
            return true;
        }

        // Check for connection-related messages
        if (str_contains($exception->getMessage(), 'connection') ||
            str_contains($exception->getMessage(), 'No suitable servers found')) {
            return true;
        }

        return false;
    }
}
