<?php

declare(strict_types=1);

namespace Workflow\Domain\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Workflow\Models\StoredWorkflow;

/**
 * Repository interface for workflow persistence operations.
 *
 * This interface abstracts database operations to support multiple storage backends
 * (SQL, MongoDB, etc.) without polluting domain logic with persistence details.
 */
interface WorkflowRepositoryInterface
{
    /**
     * Find a workflow by ID.
     *
     * @param string|int $id
     */
    public function find($id): ?StoredWorkflow;

    /**
     * Create a new workflow.
     */
    public function create(array $attributes): StoredWorkflow;

    /**
     * Update a workflow.
     */
    public function update(StoredWorkflow $workflow, array $attributes): bool;

    /**
     * Delete a workflow.
     */
    public function delete(StoredWorkflow $workflow): bool;

    /**
     * Get prunable workflows.
     */
    public function getPrunableWorkflows(): \Illuminate\Database\Eloquent\Builder;

    /**
     * Attach a child workflow to a parent.
     */
    public function attachChild(StoredWorkflow $parent, StoredWorkflow $child, array $pivotData = []): void;

    /**
     * Detach a child workflow from a parent.
     */
    public function detachChild(StoredWorkflow $parent, StoredWorkflow $child): void;

    /**
     * Get children workflows.
     */
    public function getChildren(StoredWorkflow $workflow): Collection;

    /**
     * Get parent workflows.
     */
    public function getParents(StoredWorkflow $workflow): Collection;

    /**
     * Get continued workflows.
     */
    public function getContinuedWorkflows(StoredWorkflow $workflow): Collection;

    /**
     * Get the active workflow.
     */
    public function getActiveWorkflow(StoredWorkflow $workflow): ?StoredWorkflow;
}
