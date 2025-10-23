<?php

declare(strict_types=1);

namespace Workflow\Domain\Contracts;

/**
 * Interface for handling database-specific exceptions.
 *
 * Different database backends throw different exceptions for the same scenarios
 * (e.g., duplicate keys, connection issues). This interface provides a clean
 * abstraction to detect and handle these scenarios without string checking.
 */
interface ExceptionHandlerInterface
{
    /**
     * Determine if an exception is a duplicate key violation.
     */
    public function isDuplicateKeyException(\Throwable $exception): bool;

    /**
     * Determine if an exception is a connection error.
     */
    public function isConnectionException(\Throwable $exception): bool;
}
