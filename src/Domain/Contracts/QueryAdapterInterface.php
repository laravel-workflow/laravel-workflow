<?php

declare(strict_types=1);

namespace Workflow\Domain\Contracts;

use Illuminate\Support\Collection;
use Workflow\Models\StoredWorkflow;

/**
 * Interface for handling database-specific query operations.
 *
 * Different databases may require different query strategies for optimal performance
 * (e.g., MongoDB may need manual filtering in PHP for certain queries).
 */
interface QueryAdapterInterface
{
    /**
     * Get signals for a workflow, optionally filtered by a maximum created_at timestamp.
     */
    public function getSignalsUpToTimestamp(
        StoredWorkflow $workflow,
        ?\Illuminate\Support\Carbon $maxCreatedAt = null
    ): Collection;

    /**
     * Get signals between two timestamps.
     */
    public function getSignalsBetweenTimestamps(
        StoredWorkflow $workflow,
        \Illuminate\Support\Carbon $afterTimestamp,
        ?\Illuminate\Support\Carbon $beforeTimestamp = null
    ): Collection;
}
