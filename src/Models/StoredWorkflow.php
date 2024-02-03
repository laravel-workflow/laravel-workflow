<?php

declare(strict_types=1);

namespace Workflow\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\ModelStates\HasStates;
use Workflow\States\WorkflowStatus;
use Workflow\Workflow;
use Workflow\WorkflowStub;

/**
 * @template TWorkflow of Workflow
 * @template TParentStoredWorkflow of ?self
 * @property class-string<TWorkflow> $class
 * @property (TParentStoredWorkflow is self ? object{parent_index: int, parent_now: Carbon} : null) $parents_pivot
 */
class StoredWorkflow extends Model
{
    use HasStates;

    /**
     * @var string
     */
    protected $table = 'workflows';

    /**
     * @var mixed[]
     */
    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @var array<string, class-string<WorkflowStatus>>
     */
    protected $casts = [
        'status' => WorkflowStatus::class,
    ];

    public function toWorkflow()
    {
        return WorkflowStub::fromStoredWorkflow($this);
    }

    /**
     * @return HasMany<StoredWorkflowLog>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(config('workflows.stored_workflow_log_model', StoredWorkflowLog::class));
    }

    /**
     * @return HasMany<StoredWorkflowSignal>
     */
    public function signals(): HasMany
    {
        return $this->hasMany(config('workflows.stored_workflow_signal_model', StoredWorkflowSignal::class));
    }

    /**
     * @return HasMany<StoredWorkflowTimer>
     */
    public function timers(): HasMany
    {
        return $this->hasMany(config('workflows.stored_workflow_timer_model', StoredWorkflowTimer::class));
    }

    /**
     * @return HasMany<StoredWorkflowException>
     */
    public function exceptions(): HasMany
    {
        return $this->hasMany(config('workflows.stored_workflow_exception_model', StoredWorkflowException::class));
    }

    /**
     * @return BelongsToMany<StoredWorkflow<Workflow, self>>
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(
            config('workflows.stored_workflow_model', self::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'child_workflow_id',
            'parent_workflow_id'
        )->withPivot(['parent_index', 'parent_now'])->as('parents_pivot');
    }

    /**
     * @return BelongsToMany<StoredWorkflow<Workflow, null>>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(
            config('workflows.stored_workflow_model', self::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'parent_workflow_id',
            'child_workflow_id'
        )->withPivot(['parent_index', 'parent_now'])->as('children_pivot');
    }
}
