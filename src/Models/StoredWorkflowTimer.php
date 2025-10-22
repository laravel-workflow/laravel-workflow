<?php

declare(strict_types=1);

namespace Workflow\Models;

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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // For MongoDB, we need to store datetime as string to preserve microseconds
        if (config('workflows.base_model') === 'MongoDB\\Laravel\\Eloquent\\Model') {
            return [
                'stop_at' => 'string',
            ];
        }
        
        return [
            'stop_at' => 'datetime:Y-m-d H:i:s.u',
        ];
    }
    
    /**
     * Get the stop_at attribute.
     *
     * @param  mixed  $value
     * @return \Illuminate\Support\Carbon
     */
    public function getStopAtAttribute($value)
    {
        // For MongoDB, convert string back to Carbon with microseconds
        if (config('workflows.base_model') === 'MongoDB\\Laravel\\Eloquent\\Model' && is_string($value)) {
            return \Illuminate\Support\Carbon::createFromFormat('Y-m-d H:i:s.u', $value);
        }
        
        return $value;
    }
    
    /**
     * Set the stop_at attribute.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setStopAtAttribute($value)
    {
        // For MongoDB, convert Carbon to string with microseconds
        if (config('workflows.base_model') === 'MongoDB\\Laravel\\Eloquent\\Model') {
            if ($value instanceof \Illuminate\Support\Carbon || $value instanceof \DateTimeInterface) {
                $this->attributes['stop_at'] = $value->format('Y-m-d H:i:s.u');
            } else {
                $this->attributes['stop_at'] = $value;
            }
        } else {
            $this->attributes['stop_at'] = $value;
        }
    }
}
