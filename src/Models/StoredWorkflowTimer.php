<?php

declare(strict_types=1);

namespace Workflow\Models;

use Workflow\Domain\Contracts\DateTimeAdapterInterface;

/**
 * @extends Illuminate\Database\Eloquent\Model
 */
class StoredWorkflowTimer extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var string
     */
    protected $table = 'workflow_timers';

    /**
     * @var mixed[]
     */
    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * Get the stop_at attribute.
     *
     * @param  mixed  $value
     * @return \Illuminate\Support\Carbon|null
     */
    public function getStopAtAttribute($value)
    {
        if ($value === null) {
            return null;
        }

        // If already a Carbon instance, return as-is
        if ($value instanceof \Illuminate\Support\Carbon) {
            return $value;
        }

        $adapter = app(DateTimeAdapterInterface::class);

        return $adapter->parseFromStorage($value);
    }

    /**
     * Set the stop_at attribute.
     *
     * @param  mixed  $value
     */
    public function setStopAtAttribute($value)
    {
        if ($value === null) {
            $this->attributes['stop_at'] = null;
            return;
        }

        $adapter = app(DateTimeAdapterInterface::class);

        $this->attributes['stop_at'] = $adapter->formatForStorage($value);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stop_at' => 'datetime:Y-m-d H:i:s.u',
        ];
    }
}
