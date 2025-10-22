<?php

declare(strict_types=1);

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\ModelStates\HasStates;
use Workflow\States\WorkflowContinuedStatus;
use Workflow\States\WorkflowStatus;
use Workflow\Traits\WorkflowRelationships;
use Workflow\WorkflowStub;

/**
 * @extends Illuminate\Database\Eloquent\Model
 */
class StoredWorkflow extends Model
{
    use HasStates;
    use Prunable;
    use WorkflowRelationships;

    /**
     * @var int
     */
    public const CONTINUE_PARENT_INDEX = PHP_INT_MAX;

    /**
     * @var int
     */
    public const ACTIVE_WORKFLOW_INDEX = PHP_INT_MAX - 1;

    /**
     * @var string
     */
    protected $table = 'workflows';

    /**
     * @var mixed[]
     */
    protected $guarded = [];

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @var array<string, class-string<\Workflow\States\WorkflowStatus>>
     */
    protected $casts = [
        'status' => WorkflowStatus::class,
        'id' => 'string',
    ];

    /**
     * Get the casts array.
     *
     * @return array
     */
    public function getCasts()
    {
        $casts = parent::getCasts();
        
        // For MongoDB, ensure these fields are cast as strings to avoid preg_match errors
        if (config('workflows.base_model') === 'MongoDB\\Laravel\\Eloquent\\Model') {
            $casts['class'] = 'string';
            $casts['arguments'] = 'string';
            $casts['output'] = 'string';
        }
        
        return $casts;
    }

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
        return $this->hasMany(
            config('workflows.stored_workflow_signal_model', StoredWorkflowSignal::class),
            'stored_workflow_id',
            'id'
        )->orderBy('id');
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

    public function parents(): BelongsToMany
    {
        if ($this->isMongoDBModel()) {
            return new \Workflow\Relations\MongoDBBelongsToMany(
                $this->newRelatedInstance(config('workflows.stored_workflow_model', self::class))->newQuery(),
                $this,
                config('workflows.workflow_relationships_table', 'workflow_relationships'),
                'child_workflow_id',
                'parent_workflow_id',
                $this->getKeyName(),
                $this->getRelated(config('workflows.stored_workflow_model', self::class))->getKeyName(),
                null
            );
        }

        return $this->belongsToMany(
            config('workflows.stored_workflow_model', self::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'child_workflow_id',
            'parent_workflow_id'
        )->withPivot(['parent_index', 'parent_now']);
    }

    public function children(): BelongsToMany
    {
        if ($this->isMongoDBModel()) {
            return new \Workflow\Relations\MongoDBBelongsToMany(
                $this->newRelatedInstance(config('workflows.stored_workflow_model', self::class))->newQuery(),
                $this,
                config('workflows.workflow_relationships_table', 'workflow_relationships'),
                'parent_workflow_id',
                'child_workflow_id',
                $this->getKeyName(),
                $this->getRelated(config('workflows.stored_workflow_model', self::class))->getKeyName(),
                null
            );
        }

        return $this->belongsToMany(
            config('workflows.stored_workflow_model', self::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'parent_workflow_id',
            'child_workflow_id'
        )->withPivot(['parent_index', 'parent_now']);
    }

    public function continuedWorkflows(): BelongsToMany
    {
        if ($this->isMongoDBModel()) {
            $relation = new \Workflow\Relations\MongoDBBelongsToMany(
                $this->newRelatedInstance(config('workflows.stored_workflow_model', self::class))->newQuery(),
                $this,
                config('workflows.workflow_relationships_table', 'workflow_relationships'),
                'parent_workflow_id',
                'child_workflow_id',
                $this->getKeyName(),
                $this->getRelated(config('workflows.stored_workflow_model', self::class))->getKeyName(),
                null
            );
            return $relation->wherePivot('parent_index', self::CONTINUE_PARENT_INDEX)
                ->orderBy('child_workflow_id');
        }

        return $this->belongsToMany(
            config('workflows.stored_workflow_model', self::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'parent_workflow_id',
            'child_workflow_id'
        )->wherePivot('parent_index', self::CONTINUE_PARENT_INDEX)
            ->withPivot(['parent_index', 'parent_now'])
            ->orderBy('child_workflow_id');
    }

    public function activeWorkflow(): BelongsToMany
    {
        if ($this->isMongoDBModel()) {
            $relation = new \Workflow\Relations\MongoDBBelongsToMany(
                $this->newRelatedInstance(config('workflows.stored_workflow_model', self::class))->newQuery(),
                $this,
                config('workflows.workflow_relationships_table', 'workflow_relationships'),
                'parent_workflow_id',
                'child_workflow_id',
                $this->getKeyName(),
                $this->getRelated(config('workflows.stored_workflow_model', self::class))->getKeyName(),
                null
            );
            return $relation->wherePivot('parent_index', self::ACTIVE_WORKFLOW_INDEX)
                ->orderBy('child_workflow_id');
        }

        return $this->belongsToMany(
            config('workflows.stored_workflow_model', self::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'parent_workflow_id',
            'child_workflow_id'
        )->wherePivot('parent_index', self::ACTIVE_WORKFLOW_INDEX)
            ->withPivot(['parent_index', 'parent_now'])
            ->orderBy('child_workflow_id');
    }

    protected function isMongoDBModel(): bool
    {
        return config('workflows.base_model') === 'MongoDB\\Laravel\\Eloquent\\Model' ||
            $this instanceof \MongoDB\Laravel\Eloquent\Model;
    }

    protected function getRelated(string $class)
    {
        return new $class();
    }

    public function active(): self
    {
        $active = $this->fresh();

        if ($active->status::class === WorkflowContinuedStatus::class) {
            $active = $this->activeWorkflow()
                ->first();
        }

        return $active;
    }

    public function prunable(): Builder
    {
        $query = static::where('status', 'completed')
            ->where('created_at', '<=', now()->sub(config('workflows.prune_age', '1 month')));
        
        // For MongoDB, we need to manually check if parents exist
        if (config('workflows.base_model') === 'MongoDB\\Laravel\\Eloquent\\Model') {
            // Get all child workflow IDs that have parents
            $childIds = \Workflow\Models\WorkflowRelationship::distinct('child_workflow_id')->pluck('child_workflow_id')->filter()->all();
            
            if (!empty($childIds)) {
                $query->whereNotIn('_id', $childIds);
            }
        } else {
            $query->whereDoesntHave('parents');
        }
        
        return $query;
    }

    protected function pruning(): void
    {
        $this->recursivePrune($this);
    }

    protected function recursivePrune(self $workflow): void
    {
        // Get children before detaching
        $children = $workflow->children()->get();
        
        $children->each(function ($child) {
                $this->recursivePrune($child);
            });

        $workflow->parents()
            ->detach();
        $workflow->children()
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
