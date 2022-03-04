<?php

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Model;

class StoredWorkflowLog extends Model
{
    protected $table = 'workflow_logs';

    protected $guarded = [];

    public function workflow()
    {
        return $this->belongsTo(StoredWorkflow::class);
    }
}
