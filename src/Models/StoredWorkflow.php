<?php

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
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
        'arguments' => 'array',
        'log' => AsCollection::class,
        'output' => 'array',
        'status' => WorkflowStatus::class,
    ];

    public function toWorkflow()
    {
        return WorkflowStub::fromStoredWorkflow($this);
    }
}
