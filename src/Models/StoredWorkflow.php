<?php

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\ModelStates\HasStates;
use Workflow\States\WorkflowStatus;
use Workflow\WorkflowStub;

class StoredWorkflow extends Model
{
    use HasStates;

    protected $table = 'workflows';

    protected $guarded = [];

    protected $casts = [
        'status' => WorkflowStatus::class,
    ];

    public function toWorkflow()
    {
        return WorkflowStub::fromStoredWorkflow($this);
    }

    public function logs()
    {
        return $this->hasMany(StoredWorkflowLog::class);
    }

    public function signals()
    {
        return $this->hasMany(StoredWorkflowSignal::class);
    }

    public function timers()
    {
        return $this->hasMany(StoredWorkflowTimer::class);
    }
}
