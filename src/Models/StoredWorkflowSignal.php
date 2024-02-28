<?php

declare(strict_types=1);

namespace Workflow\Models;

class StoredWorkflowSignal extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'workflow_signals';

    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';
}
