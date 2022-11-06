<?php

declare(strict_types=1);

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class StoredWorkflowTimer extends Model
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

    /**
     * @var array<string, class-string<\datetime>>
     */
    protected $casts = [
        'stop_at' => 'datetime',
    ];

    public function getCreatedAtAttribute(string $value): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s.u', $value);
    }

    public function setCreatedAtAttribute(Carbon $value): void
    {
        $this->attributes['created_at'] = $value->format('Y-m-d H:i:s.u');
    }

    public function getStopAtAttribute(string $value): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s.u', $value);
    }

    public function setStopAtAttribute(Carbon $value): void
    {
        $this->attributes['stop_at'] = $value->format('Y-m-d H:i:s.u');
    }

    public function workflow(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StoredWorkflow::class);
    }
}
