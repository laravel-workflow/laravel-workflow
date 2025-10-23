<?php

declare(strict_types=1);

namespace Workflow\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Collection;
use Workflow\Domain\Contracts\WorkflowRepositoryInterface;
use Workflow\Models\StoredWorkflow;

/**
 * Eloquent/SQL implementation of the workflow repository.
 */
class EloquentWorkflowRepository implements WorkflowRepositoryInterface
{
    public function find($id): ?StoredWorkflow
    {
        return StoredWorkflow::find($id);
    }

    public function create(array $attributes): StoredWorkflow
    {
        return StoredWorkflow::create($attributes);
    }

    public function update(StoredWorkflow $workflow, array $attributes): bool
    {
        return $workflow->update($attributes);
    }

    public function delete(StoredWorkflow $workflow): bool
    {
        return $workflow->delete();
    }

    public function getPrunableWorkflows(): \Illuminate\Database\Eloquent\Builder
    {
        return StoredWorkflow::where('status', 'completed')
            ->where('created_at', '<=', now()->sub(config('workflows.prune_age', '1 month')))
            ->whereDoesntHave('parents');
    }

    public function attachChild(StoredWorkflow $parent, StoredWorkflow $child, array $pivotData = []): void
    {
        $parent->children()
            ->attach($child->getKey(), $pivotData);
    }

    public function detachChild(StoredWorkflow $parent, StoredWorkflow $child): void
    {
        $parent->children()
            ->detach($child->getKey());
    }

    public function getChildren(StoredWorkflow $workflow): Collection
    {
        return $workflow->children()
            ->get();
    }

    public function getParents(StoredWorkflow $workflow): Collection
    {
        return $workflow->parents()
            ->get();
    }

    public function getContinuedWorkflows(StoredWorkflow $workflow): Collection
    {
        return $workflow->continuedWorkflows()
            ->get();
    }

    public function getActiveWorkflow(StoredWorkflow $workflow): ?StoredWorkflow
    {
        return $workflow->activeWorkflow()
            ->first();
    }
}
