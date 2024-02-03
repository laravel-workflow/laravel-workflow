<?php

declare(strict_types=1);

namespace Workflow\Models;

class StoredWorkflowTimer extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'workflow_timers';

    protected $guarded = [];

    protected $casts = [
        'stop_at' => 'datetime:Y-m-d H:i:s.u',
    ];
}
