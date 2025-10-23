<?php

declare(strict_types=1);

namespace Workflow\Infrastructure\Persistence\MongoDB;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Workflow\Domain\Contracts\QueryAdapterInterface;
use Workflow\Models\StoredWorkflow;

/**
 * MongoDB query adapter that handles MongoDB-specific query patterns.
 *
 * Some queries are more efficient when done in PHP due to MongoDB driver limitations.
 */
class MongoDBQueryAdapter implements QueryAdapterInterface
{
    public function getSignalsUpToTimestamp(StoredWorkflow $workflow, ?Carbon $maxCreatedAt = null): Collection
    {
        // For MongoDB, get all signals for the workflow and filter in PHP
        // This avoids issues with MongoDB datetime comparison queries
        $connection = \Illuminate\Support\Facades\DB::connection(config('database.default'));
        $collection = $connection->getCollection('workflow_signals');

        $allSignals = $collection->find([], [
            'sort' => [
                '_id' => 1,
            ],
        ])->toArray();

        $filtered = collect($allSignals)
            ->filter(function ($signalData) use ($workflow, $maxCreatedAt) {
                // Filter by stored_workflow_id
                if ($signalData['stored_workflow_id'] !== $workflow->id) {
                    return false;
                }

                // Filter by created_at if specified
                if ($maxCreatedAt && isset($signalData['created_at'])) {
                    $signalCreatedAt = $this->convertToCarbon($signalData['created_at']);

                    if ($signalCreatedAt && $signalCreatedAt->greaterThan($maxCreatedAt)) {
                        return false;
                    }
                }

                return true;
            })
            ->map(static function ($signalData) {
                // Convert to model-like object for consistency
                return (object) [
                    'method' => $signalData['method'],
                    'arguments' => $signalData['arguments'] ?? '[]',
                ];
            });

        return $filtered;
    }

    public function getSignalsBetweenTimestamps(
        StoredWorkflow $workflow,
        Carbon $afterTimestamp,
        ?Carbon $beforeTimestamp = null
    ): Collection {
        // For MongoDB, get all signals for the workflow and filter in PHP
        $connection = \Illuminate\Support\Facades\DB::connection(config('database.default'));
        $collection = $connection->getCollection('workflow_signals');

        $allSignals = $collection->find([], [
            'sort' => [
                '_id' => 1,
            ],
        ])->toArray();

        $filtered = collect($allSignals)
            ->filter(function ($signalData) use ($workflow, $afterTimestamp, $beforeTimestamp) {
                // Filter by stored_workflow_id
                if ($signalData['stored_workflow_id'] !== $workflow->id) {
                    return false;
                }

                if (! isset($signalData['created_at'])) {
                    return false;
                }

                $signalCreatedAt = $this->convertToCarbon($signalData['created_at']);

                if (! $signalCreatedAt) {
                    return false;
                }

                // Must be after the after timestamp
                if ($signalCreatedAt->lessThanOrEqualTo($afterTimestamp)) {
                    return false;
                }

                // Must be before the before timestamp if specified
                if ($beforeTimestamp && $signalCreatedAt->greaterThan($beforeTimestamp)) {
                    return false;
                }

                return true;
            })
            ->map(static function ($signalData) {
                // Convert to model-like object for consistency
                return (object) [
                    'method' => $signalData['method'],
                    'arguments' => $signalData['arguments'] ?? '[]',
                ];
            });

        return $filtered;
    }

    /**
     * Convert MongoDB date to Carbon instance.
     *
     * @param mixed $value
     */
    private function convertToCarbon($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        // MongoDB UTCDateTime object
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            return Carbon::instance($value->toDateTime());
        }

        // String date
        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Exception $e) {
                return null;
            }
        }

        // DateTime object
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        return null;
    }
}
