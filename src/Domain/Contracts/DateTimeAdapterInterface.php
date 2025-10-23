<?php

declare(strict_types=1);

namespace Workflow\Domain\Contracts;

/**
 * Interface for handling database-specific datetime operations.
 *
 * Different databases handle microseconds differently (e.g., MongoDB needs string conversion,
 * SQL can use native datetime with microseconds).
 */
interface DateTimeAdapterInterface
{
    /**
     * Get the casts array for a model.
     */
    public function getCasts(array $baseCasts): array;

    /**
     * Format a datetime value for storage.
     *
     * @param mixed $value
     * @return mixed
     */
    public function formatForStorage($value, string $format = 'Y-m-d H:i:s.u');

    /**
     * Parse a datetime value from storage.
     *
     * @param mixed $value
     * @return \Illuminate\Support\Carbon|null
     */
    public function parseFromStorage($value, string $format = 'Y-m-d H:i:s.u');
}
