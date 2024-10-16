<?php

declare(strict_types=1);

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Spatie\ModelStates\HasStates;
use Workflow\States\WorkflowStatus;
use Workflow\WorkflowStub;

/**
 * @extends Illuminate\Database\Eloquent\Model
 */
class StoredWorkflow extends Model
{
    use HasStates;
    use Prunable;

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
     * @var array<string, class-string<\Workflow\States\WorkflowStatus>>
     */
    protected $casts = [
        'status' => WorkflowStatus::class,
    ];

    public function toWorkflow()
    {
        return WorkflowStub::fromStoredWorkflow($this);
    }

    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(config('workflows.stored_workflow_log_model', StoredWorkflowLog::class))
            ->orderBy('id');
    }

    public function signals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(config('workflows.stored_workflow_signal_model', StoredWorkflowSignal::class))
            ->orderBy('id');
    }

    public function timers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(config('workflows.stored_workflow_timer_model', StoredWorkflowTimer::class))
            ->orderBy('id');
    }

    public function exceptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(config('workflows.stored_workflow_exception_model', StoredWorkflowException::class))
            ->orderBy('id');
    }

    public function parents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            config('workflows.stored_workflow_model', self::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'child_workflow_id',
            'parent_workflow_id'
        )->withPivot(['parent_index', 'parent_now']);
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            config('workflows.stored_workflow_model', self::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'parent_workflow_id',
            'child_workflow_id'
        )->withPivot(['parent_index', 'parent_now']);
    }

    public function prunable(): Builder
    {
        return static::where('status', 'completed')
            ->where('created_at', '<=', now()->sub(config('workflows.prune_age', '1 month')))
            ->whereDoesntHave('parents');
    }

    protected function pruning(): void
    {
        $this->recursivePrune($this);
    }

    protected function recursivePrune(self $workflow): void
    {
        $workflow->children()
            ->each(function ($child) {
                $this->recursivePrune($child);
            });

        $workflow->parents()
            ->detach();
        $workflow->exceptions()
            ->delete();
        $workflow->logs()
            ->delete();
        $workflow->signals()
            ->delete();
        $workflow->timers()
            ->delete();

        if ($workflow->id !== $this->id) {
            $workflow->delete();
        }
    }
}
