<?php

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Model;

class StoredWorkflowSignal extends Model
{
    protected $table = 'workflow_signals';

    protected $guarded = [];

    public function workflow()
    {
        return $this->belongsTo(StoredWorkflow::class);
    }
}
