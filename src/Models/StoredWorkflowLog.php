<?php

declare(strict_types=1);

namespace Workflow\Models;

class StoredWorkflowLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'workflow_logs';

    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $casts = [
        'now' => 'datetime',
    ];
}
