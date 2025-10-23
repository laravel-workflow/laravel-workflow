<?php

declare(strict_types=1);

namespace Workflow\Infrastructure\Persistence\MongoDB;

use Illuminate\Support\Carbon;
use Workflow\Domain\Contracts\DateTimeAdapterInterface;

/**
 * MongoDB datetime adapter that stores datetime as strings to preserve microseconds.
 */
class MongoDBDateTimeAdapter implements DateTimeAdapterInterface
{
    public function getCasts(array $baseCasts): array
    {
        // MongoDB Laravel handles datetime casts natively, converting UTCDateTime to Carbon
        // We only need to override casts that cause issues with MongoDB's attribute handling
        
        $mongoDbCasts = [];

        // Add MongoDB-specific string casts for fields that may have preg_match issues
        $mongoDbCasts['class'] = 'string';
        $mongoDbCasts['arguments'] = 'string';
        $mongoDbCasts['output'] = 'string';

        return array_merge($baseCasts, $mongoDbCasts);
    }

    public function formatForStorage($value, string $format = 'Y-m-d H:i:s.u')
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        return $value;
    }

    public function parseFromStorage($value, string $format = 'Y-m-d H:i:s.u')
    {
        if ($value === null) {
            return null;
        }

        // Handle MongoDB UTCDateTime objects
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            return Carbon::createFromTimestampMs($value->toDateTime()->getTimestamp() * 1000);
        }

        // Handle arrays (serialized UTCDateTime)
        if (is_array($value) && isset($value['date'])) {
            return Carbon::parse($value['date']);
        }

        if (is_string($value)) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Exception $e) {
                // Fallback to default parsing
                try {
                    return Carbon::parse($value);
                } catch (\Exception $e2) {
                    return null;
                }
            }
        }

        // If already Carbon/DateTime, return as-is
        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value);
        }

        return $value;
    }
}
