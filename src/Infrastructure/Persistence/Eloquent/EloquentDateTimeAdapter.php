<?php

declare(strict_types=1);

namespace Workflow\Infrastructure\Persistence\Eloquent;

use Workflow\Domain\Contracts\DateTimeAdapterInterface;

/**
 * Eloquent/SQL datetime adapter using native database datetime with microseconds.
 */
class EloquentDateTimeAdapter implements DateTimeAdapterInterface
{
    public function getCasts(array $baseCasts): array
    {
        // SQL databases support datetime with microseconds natively
        // No additional casts needed
        return $baseCasts;
    }

    public function formatForStorage($value, string $format = 'Y-m-d H:i:s.u')
    {
        // Eloquent handles datetime conversion automatically for SQL databases
        // Just return the value as-is
        return $value;
    }

    public function parseFromStorage($value, string $format = 'Y-m-d H:i:s.u')
    {
        // Eloquent handles datetime parsing automatically for SQL databases
        // Just return the value as-is
        if ($value === null) {
            return null;
        }

        return $value;
    }
}
