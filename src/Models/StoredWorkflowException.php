<?php

declare(strict_types=1);

namespace Workflow\Models;

class StoredWorkflowException extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'workflow_exceptions';

    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';
}
