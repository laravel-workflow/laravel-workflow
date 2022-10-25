<?php

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Model;

class StoredWorkflowTimer extends Model
{
    protected $table = 'workflow_timers';

    protected $guarded = [];

    protected $casts = [
        'stop_at' => 'datetime',
    ];

    public function workflow()
    {
        return $this->belongsTo(StoredWorkflow::class);
    }
}
