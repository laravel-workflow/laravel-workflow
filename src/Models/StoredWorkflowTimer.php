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
     * @var array<string, class-string<\datetime>>
     */
    protected $casts = [
        'stop_at' => 'datetime:Y-m-d H:i:s.u',
    ];
}
