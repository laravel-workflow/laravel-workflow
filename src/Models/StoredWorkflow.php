<?php

declare(strict_types=1);

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\ModelStates\HasStates;
use Workflow\Domain\Contracts\DateTimeAdapterInterface;
use Workflow\Domain\Contracts\RelationshipAdapterInterface;
use Workflow\Domain\Contracts\WorkflowRepositoryInterface;
use Workflow\States\WorkflowContinuedStatus;
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

        // Use the DateTimeAdapter to handle database-specific casting
        $adapter = app(DateTimeAdapterInterface::class);

        return $adapter->getCasts($casts);
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
        $adapter = app(RelationshipAdapterInterface::class);

        return $adapter->createParentsRelation(
            $this,
            config('workflows.stored_workflow_model', self::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'child_workflow_id',
            'parent_workflow_id'
        );
    }

    public function children(): BelongsToMany
    {
        $adapter = app(RelationshipAdapterInterface::class);

        return $adapter->createChildrenRelation(
            $this,
            config('workflows.stored_workflow_model', self::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'parent_workflow_id',
            'child_workflow_id'
        );
    }

    public function continuedWorkflows(): BelongsToMany
    {
        return $this->children()
            ->wherePivot('parent_index', self::CONTINUE_PARENT_INDEX)
            ->orderBy('child_workflow_id');
    }

    public function activeWorkflow(): BelongsToMany
    {
        return $this->children()
            ->wherePivot('parent_index', self::ACTIVE_WORKFLOW_INDEX)
            ->orderBy('child_workflow_id');
    }

    public function active(): self
    {
        $active = $this->fresh();

        if ($active === null) {
            return $this;
        }

        if ($active->status::class === WorkflowContinuedStatus::class) {
            $continued = $this->activeWorkflow()
                ->first();

            if ($continued !== null) {
                $active = $continued;
            }
        }

        return $active;
    }

    public function prunable(): Builder
    {
        $repository = app(WorkflowRepositoryInterface::class);

        return $repository->getPrunableWorkflows();
    }

    protected function pruning(): void
    {
        $this->recursivePrune($this);
    }

    protected function recursivePrune(self $workflow): void
    {
        // Get children before detaching
        $children = $workflow->children()
            ->get();

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
