<?php

declare(strict_types=1);

namespace Workflow\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Workflow\Models\WorkflowRelationship;

/**
 * This trait provides MongoDB-compatible workflow relationships using a separate
 * pivot collection instead of embedded arrays, allowing us to store pivot attributes
 * like parent_index and parent_now.
 */
trait MongoDBWorkflowRelationships
{
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(
            config('workflows.stored_workflow_model', \Workflow\Models\StoredWorkflow::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'parent_workflow_id',
            'child_workflow_id'
        )
            ->using(WorkflowRelationship::class)
            ->withPivot(['parent_index', 'parent_now'])
            ->withTimestamps();
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(
            config('workflows.stored_workflow_model', \Workflow\Models\StoredWorkflow::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'child_workflow_id',
            'parent_workflow_id'
        )
            ->using(WorkflowRelationship::class)
            ->withPivot(['parent_index', 'parent_now'])
            ->withTimestamps();
    }

    public function continuedWorkflows(): BelongsToMany
    {
        return $this->belongsToMany(
            config('workflows.stored_workflow_model', \Workflow\Models\StoredWorkflow::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'parent_workflow_id',
            'child_workflow_id'
        )
            ->using(WorkflowRelationship::class)
            ->wherePivot('parent_index', self::CONTINUE_PARENT_INDEX)
            ->withPivot(['parent_index', 'parent_now'])
            ->withTimestamps()
            ->orderBy('child_workflow_id');
    }

    public function activeWorkflow(): BelongsToMany
    {
        return $this->belongsToMany(
            config('workflows.stored_workflow_model', \Workflow\Models\StoredWorkflow::class),
            config('workflows.workflow_relationships_table', 'workflow_relationships'),
            'parent_workflow_id',
            'child_workflow_id'
        )
            ->using(WorkflowRelationship::class)
            ->wherePivot('parent_index', self::ACTIVE_WORKFLOW_INDEX)
            ->withPivot(['parent_index', 'parent_now'])
            ->withTimestamps()
            ->orderBy('child_workflow_id');
    }
}
