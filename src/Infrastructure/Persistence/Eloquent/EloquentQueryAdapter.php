<?php

declare(strict_types=1);

namespace Workflow\Infrastructure\Persistence\Eloquent;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Workflow\Domain\Contracts\QueryAdapterInterface;
use Workflow\Models\StoredWorkflow;

/**
 * Eloquent/SQL query adapter using standard Eloquent query builder.
 */
class EloquentQueryAdapter implements QueryAdapterInterface
{
    public function getSignalsUpToTimestamp(StoredWorkflow $workflow, ?Carbon $maxCreatedAt = null): Collection
    {
        $query = $workflow->signals();

        if ($maxCreatedAt) {
            $query->where('created_at', '<=', $maxCreatedAt->format('Y-m-d H:i:s.u'));
        }

        return $query->get();
    }

    public function getSignalsBetweenTimestamps(
        StoredWorkflow $workflow,
        Carbon $afterTimestamp,
        ?Carbon $beforeTimestamp = null
    ): Collection {
        $query = $workflow->signals()
            ->where('created_at', '>', $afterTimestamp->format('Y-m-d H:i:s.u'));

        if ($beforeTimestamp) {
            $query->where('created_at', '<=', $beforeTimestamp->format('Y-m-d H:i:s.u'));
        }

        return $query->get();
    }
}
