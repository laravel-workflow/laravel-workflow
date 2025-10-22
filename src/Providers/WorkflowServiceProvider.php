<?php

declare(strict_types=1);

namespace Workflow\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Laravel\SerializableClosure\SerializableClosure;
use Workflow\Commands\ActivityMakeCommand;
use Workflow\Commands\WorkflowMakeCommand;

final class WorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workflows.php', 'workflows');
    }

    public function boot(): void
    {
        if (! class_exists('Workflow\Models\Model')) {
            class_alias(config('workflows.base_model', Model::class), 'Workflow\Models\Model');
        }

        SerializableClosure::setSecretKey(config('app.key'));

        $this->publishes([
            __DIR__ . '/../config/workflows.php' => config_path('workflows.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../migrations/' => database_path('/migrations'),
        ], 'migrations');

        $this->commands([ActivityMakeCommand::class, WorkflowMakeCommand::class]);

        // Create MongoDB indexes if using MongoDB
        if (config('workflows.base_model') === 'MongoDB\\Laravel\\Eloquent\\Model') {
            $this->createMongoDBIndexes();
        }
    }

    /**
     * Create necessary unique indexes for MongoDB collections.
     */
    protected function createMongoDBIndexes(): void
    {
        try {
            $connection = app('db')->connection(config('database.default'));
            $collection = $connection->getCollection('workflow_logs');
            
            // Try to create unique index on workflow_logs (stored_workflow_id, index)
            try {
                $collection->createIndex(
                    ['stored_workflow_id' => 1, 'index' => 1],
                    ['unique' => true, 'background' => false]
                );
            } catch (\Exception $e) {
                // If index creation fails due to duplicates in existing data, just drop and recreate
                // In production, this will only happen once during initial deployment
                if (str_contains($e->getMessage(), 'E11000') || str_contains($e->getMessage(), 'duplicate key')) {
                    try {
                        $collection->dropIndex('stored_workflow_id_1_index_1');
                        // Delete duplicate entries to allow index creation
                        // This uses an aggregation to find and keep only the first occurrence of each duplicate
                        $pipeline = [
                            ['$group' => [
                                '_id' => ['stored_workflow_id' => '$stored_workflow_id', 'index' => '$index'],
                                'ids' => ['$push' => '$_id'],
                                'count' => ['$sum' => 1]
                            ]],
                            ['$match' => ['count' => ['$gt' => 1]]]
                        ];
                        $duplicates = $collection->aggregate($pipeline);
                        foreach ($duplicates as $dup) {
                            // Keep first, delete rest
                            $idsToDelete = array_slice($dup['ids'], 1);
                            $collection->deleteMany(['_id' => ['$in' => $idsToDelete]]);
                        }
                        $collection->createIndex(
                            ['stored_workflow_id' => 1, 'index' => 1],
                            ['unique' => true, 'background' => false]
                        );
                    } catch (\Exception $e2) {
                        // Failed to recreate, give up
                    }
                }
            }
        } catch (\Exception $e) {
            // MongoDB might not be available yet
        }
    }
}
