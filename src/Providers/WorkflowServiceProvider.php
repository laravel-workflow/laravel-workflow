<?php

declare(strict_types=1);

namespace Workflow\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Laravel\SerializableClosure\SerializableClosure;
use Workflow\Commands\ActivityMakeCommand;
use Workflow\Commands\WorkflowMakeCommand;
use Workflow\Domain\Contracts\DateTimeAdapterInterface;
use Workflow\Domain\Contracts\ExceptionHandlerInterface;
use Workflow\Domain\Contracts\QueryAdapterInterface;
use Workflow\Domain\Contracts\RelationshipAdapterInterface;
use Workflow\Domain\Contracts\WorkflowRepositoryInterface;
use Workflow\Infrastructure\Persistence\Eloquent\EloquentDateTimeAdapter;
use Workflow\Infrastructure\Persistence\Eloquent\EloquentExceptionHandler;
use Workflow\Infrastructure\Persistence\Eloquent\EloquentQueryAdapter;
use Workflow\Infrastructure\Persistence\Eloquent\EloquentRelationshipAdapter;
use Workflow\Infrastructure\Persistence\Eloquent\EloquentWorkflowRepository;
use Workflow\Infrastructure\Persistence\MongoDB\MongoDBDateTimeAdapter;
use Workflow\Infrastructure\Persistence\MongoDB\MongoDBExceptionHandler;
use Workflow\Infrastructure\Persistence\MongoDB\MongoDBQueryAdapter;
use Workflow\Infrastructure\Persistence\MongoDB\MongoDBRelationshipAdapter;
use Workflow\Infrastructure\Persistence\MongoDB\MongoDBWorkflowRepository;

final class WorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/workflows.php', 'workflows');

        // Register adapters based on database configuration
        $this->registerAdapters();
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
        if ($this->isMongoDBConnection()) {
            $this->createMongoDBIndexes();
        }
    }

    /**
     * Register the appropriate adapters based on database configuration.
     */
    protected function registerAdapters(): void
    {
        if ($this->isMongoDBConnection()) {
            // MongoDB adapters
            $this->app->singleton(WorkflowRepositoryInterface::class, MongoDBWorkflowRepository::class);
            $this->app->singleton(RelationshipAdapterInterface::class, MongoDBRelationshipAdapter::class);
            $this->app->singleton(DateTimeAdapterInterface::class, MongoDBDateTimeAdapter::class);
            $this->app->singleton(QueryAdapterInterface::class, MongoDBQueryAdapter::class);
            $this->app->singleton(ExceptionHandlerInterface::class, MongoDBExceptionHandler::class);
        } else {
            // Eloquent/SQL adapters
            $this->app->singleton(WorkflowRepositoryInterface::class, EloquentWorkflowRepository::class);
            $this->app->singleton(RelationshipAdapterInterface::class, EloquentRelationshipAdapter::class);
            $this->app->singleton(DateTimeAdapterInterface::class, EloquentDateTimeAdapter::class);
            $this->app->singleton(QueryAdapterInterface::class, EloquentQueryAdapter::class);
            $this->app->singleton(ExceptionHandlerInterface::class, EloquentExceptionHandler::class);
        }
    }

    /**
     * Determine if the application is using a MongoDB connection.
     *
     * This checks the configured base_model class to detect if MongoDB is being used.
     * Users can override base_model in their published config to use MongoDB.
     */
    protected function isMongoDBConnection(): bool
    {
        $baseModel = config('workflows.base_model', Model::class);

        // Check if the base model is MongoDB's Eloquent Model
        if ($baseModel === 'MongoDB\\Laravel\\Eloquent\\Model') {
            return true;
        }

        // Check if it's a subclass of MongoDB's Model
        if (class_exists($baseModel) && class_exists('MongoDB\\Laravel\\Eloquent\\Model')) {
            return is_subclass_of($baseModel, 'MongoDB\\Laravel\\Eloquent\\Model');
        }

        return false;
    }

    /**
     * Create necessary unique indexes for MongoDB collections.
     */
    protected function createMongoDBIndexes(): void
    {
        try {
            $connection = app('db')
                ->connection(config('database.default'));
            $collection = $connection->getCollection('workflow_logs');

            // Try to create unique index on workflow_logs (stored_workflow_id, index)
            try {
                $collection->createIndex(
                    [
                        'stored_workflow_id' => 1,
                        'index' => 1,
                    ],
                    [
                        'unique' => true,
                        'background' => false,
                    ]
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
                            [
                                '$group' => [
                                    '_id' => [
                                        'stored_workflow_id' => '$stored_workflow_id',
                                        'index' => '$index',
                                    ],
                                    'ids' => [
                                        '$push' => '$_id',
                                    ],
                                    'count' => [
                                        '$sum' => 1,
                                    ],
                                ],
                            ],
                            [
                                '$match' => [
                                    'count' => [
                                        '$gt' => 1,
                                    ],
                                ],
                            ],
                        ];
                        $duplicates = $collection->aggregate($pipeline);
                        foreach ($duplicates as $dup) {
                            // Keep first, delete rest
                            $idsToDelete = array_slice($dup['ids'], 1);
                            $collection->deleteMany([
                                '_id' => [
                                    '$in' => $idsToDelete,
                                ],
                            ]);
                        }
                        $collection->createIndex(
                            [
                                'stored_workflow_id' => 1,
                                'index' => 1,
                            ],
                            [
                                'unique' => true,
                                'background' => false,
                            ]
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
