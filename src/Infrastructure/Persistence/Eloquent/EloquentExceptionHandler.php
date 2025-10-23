<?php

declare(strict_types=1);

namespace Workflow\Infrastructure\Persistence\Eloquent;

use Workflow\Domain\Contracts\ExceptionHandlerInterface;

/**
 * Eloquent/SQL exception handler for standard Laravel SQL exceptions.
 */
class EloquentExceptionHandler implements ExceptionHandlerInterface
{
    public function isDuplicateKeyException(\Throwable $exception): bool
    {
        // Laravel 11+ has a dedicated exception for unique constraint violations
        if ($exception instanceof \Illuminate\Database\UniqueConstraintViolationException) {
            return true;
        }

        // Laravel 10 and earlier use QueryException
        if ($exception instanceof \Illuminate\Database\QueryException) {
            // MySQL error code 1062, PostgreSQL 23505, SQLite UNIQUE constraint
            $errorCode = $exception->getCode();
            return in_array($errorCode, ['23000', '23505', '1062'], true) ||
                   str_contains($exception->getMessage(), 'UNIQUE constraint');
        }

        return false;
    }

    public function isConnectionException(\Throwable $exception): bool
    {
        if ($exception instanceof \Illuminate\Database\QueryException) {
            // Check for common connection error codes
            $errorCode = $exception->getCode();
            return in_array($errorCode, ['08001', '08006', '2002', '2006'], true);
        }

        return false;
    }
}
