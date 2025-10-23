<?php

declare(strict_types=1);

namespace Workflow\Infrastructure\Persistence\MongoDB;

use Illuminate\Support\Carbon;
use MongoDB\BSON\UTCDateTime;
use Workflow\Models\WorkflowRelationship;

/**
 * MongoDB-specific WorkflowRelationship model.
 *
 * This extends the base WorkflowRelationship to handle MongoDB's UTCDateTime objects.
 * MongoDB stores dates as UTCDateTime, which Laravel's normal datetime casting can't handle.
 *
 * @internal This is an infrastructure concern and should not be used directly.
 */
class MongoDBWorkflowRelationship extends WorkflowRelationship
{
    /**
     * Get the parent_now attribute, converting MongoDB UTCDateTime to Carbon.
     */
    public function getParentNowAttribute($value)
    {
        if ($value instanceof UTCDateTime) {
            return Carbon::parse($value->toDateTime());
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value);
        }

        if (is_string($value)) {
            return Carbon::parse($value);
        }

        return $value;
    }

    /**
     * Set the parent_now attribute, converting to UTCDateTime for MongoDB.
     */
    public function setParentNowAttribute($value)
    {
        if ($value instanceof Carbon || $value instanceof \DateTimeInterface) {
            $this->attributes['parent_now'] = new UTCDateTime($value);
        } elseif (is_string($value)) {
            $this->attributes['parent_now'] = new UTCDateTime(Carbon::parse($value));
        } else {
            $this->attributes['parent_now'] = $value;
        }
    }
}
