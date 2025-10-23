<?php

declare(strict_types=1);

namespace Workflow\Infrastructure\Persistence\MongoDB;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * MongoDB BelongsToMany Workaround
 *
 * This is an unfortunate necessity due to MongoDB Laravel's limitations with pivot tables.
 * MongoDB Laravel doesn't properly support pivot collections with custom attributes
 * (like parent_index, parent_now), so we need this custom implementation.
 *
 * This class:
 * - Uses an actual pivot collection (workflow_relationships) instead of embedded arrays
 * - Supports wherePivot() and withPivot() for custom pivot attributes
 * - Works around MongoDB Laravel's lack of join support
 *
 * @internal This is an infrastructure concern and should not be used directly.
 *           Use the RelationshipAdapterInterface instead.
 */
class MongoDBBelongsToManyRelation extends BelongsToMany
{
    /**
     * The pivot where clauses that have been set on this relationship.
     *
     * @var array
     */
    protected $customPivotWheres = [];

    /**
     * Whether the join constraints have been applied.
     *
     * @var bool
     */
    protected $joinApplied = false;

    /**
     * Attach a model to the parent using a pivot collection.
     *
     * @param mixed $id
     * @param bool $touch
     */
    public function attach($id, array $attributes = [], $touch = true)
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        if ($id instanceof Collection) {
            $id = $id->modelKeys();
        }

        $ids = (array) $id;

        foreach ($ids as $relatedId) {
            // Always create the relationship record (multiple relationships between same entities are allowed with different pivot data)
            MongoDBWorkflowRelationship::create(array_merge([
                $this->foreignPivotKey => $this->parent->getKey(),
                $this->relatedPivotKey => $relatedId,
            ], $attributes));
        }

        if ($touch) {
            $this->touchIfTouching();
        }
    }

    /**
     * Detach models from the relationship using the pivot collection.
     *
     * @param mixed $ids
     * @param bool $touch
     * @return int
     */
    public function detach($ids = null, $touch = true)
    {
        if ($ids instanceof Model) {
            $ids = $ids->getKey();
        }

        if ($ids instanceof Collection) {
            $ids = $ids->modelKeys();
        }

        $query = MongoDBWorkflowRelationship::where($this->foreignPivotKey, $this->parent->getKey());

        if ($ids !== null) {
            $query->whereIn($this->relatedPivotKey, (array) $ids);
        }

        // Apply custom pivot where clauses
        foreach ($this->customPivotWheres as $whereArgs) {
            [$column, $operator, $value] = array_pad($whereArgs, 3, null);
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }
            $query->where($column, $operator, $value);
        }

        // Execute delete and return the count of deleted records
        $deleted = $query->delete();

        if ($touch) {
            $this->touchIfTouching();
        }

        return $deleted;
    }

    /**
     * Add the constraints for a relationship query.
     */
    public function addConstraints()
    {
        // Don't apply join here - it will be applied when the query is executed
        // This allows wherePivot to be called after the relation is created
    }

    /**
     * Add a "where" clause for a pivot table column to the query.
     *
     * @param  string  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function wherePivot($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->customPivotWheres[] = func_get_args();

        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        // Apply the join constraints before executing the query
        $this->setJoin();

        $models = parent::get($columns);

        // Attach pivot data to each model
        if ($models->isNotEmpty()) {
            $this->hydratePivotRelation($models->all());
        }

        return $models;
    }

    /**
     * Get the first related model record matching the attributes or instantiate it.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function first($columns = ['*'])
    {
        // Apply the join constraints before executing the query
        $this->setJoin();

        $model = parent::first($columns);

        // Attach pivot data if model was found
        if ($model) {
            $this->hydratePivotRelation([$model]);
        }

        return $model;
    }

    /**
     * Execute the query and get the first result.
     *
     * @return \Illuminate\Database\Eloquent\Model|static|null
     */
    public function count()
    {
        // Apply the join constraints before executing the query
        $this->setJoin();

        return parent::count();
    }

    /**
     * Chunk the results of the query.
     *
     * @param  int  $count
     * @return bool
     */
    public function chunk($count, callable $callback)
    {
        // Apply the join constraints before executing the query
        $this->setJoin();

        return parent::chunk($count, $callback);
    }

    /**
     * Execute a callback over each item while chunking.
     *
     * @param  int  $count
     * @return bool
     */
    public function each(callable $callback, $count = 1000)
    {
        // Apply the join constraints before executing the query
        $this->setJoin();

        return parent::each($callback, $count);
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getQualifiedForeignPivotKeyName()
    {
        return $this->foreignPivotKey;
    }

    /**
     * Get the qualified related pivot key name.
     *
     * @return string
     */
    public function getQualifiedRelatedPivotKeyName()
    {
        return $this->relatedPivotKey;
    }

    /**
     * Get the key name of the parent model.
     *
     * @return string
     */
    public function getOwnerKeyName()
    {
        return $this->parentKey;
    }

    /**
     * Get the qualified key name of the parent model.
     *
     * @return string
     */
    public function getQualifiedParentKeyName()
    {
        return $this->parent->getQualifiedKeyName();
    }

    /**
     * Set the join clause for the relation query.
     *
     * @param \Illuminate\Database\Eloquent\Builder|null $query
     * @return $this
     */
    protected function setJoin($query = null)
    {
        // Only apply join constraints once
        if ($this->joinApplied) {
            return $this;
        }

        $this->joinApplied = true;
        $query = $query ?: $this->query;

        // Get pivot IDs that match our parent
        $parentKey = $this->parent->getKey();

        if ($parentKey === null) {
            // Parent doesn't have a key yet, return empty result
            $query->where('_id', '=', '__MONGODB_NO_RESULTS__');
            return $this;
        }

        $pivotQuery = MongoDBWorkflowRelationship::where($this->foreignPivotKey, $parentKey);

        // Apply custom pivot where clauses
        foreach ($this->customPivotWheres as $whereArgs) {
            [$column, $operator, $value] = array_pad($whereArgs, 3, null);
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }
            $pivotQuery->where($column, $operator, $value);
        }

        $pivotRecords = $pivotQuery->get();
        $relatedIds = $pivotRecords->pluck($this->relatedPivotKey)
            ->filter()
            ->values()
            ->all();

        if (empty($relatedIds)) {
            // No related records, so return empty result using a condition that's always false
            $query->where('_id', '=', '__MONGODB_NO_RESULTS__');
        } else {
            // Use _id for MongoDB - ensure we select all columns
            $query->whereIn('_id', $relatedIds)
                ->select('*');
        }

        return $this;
    }

    /**
     * Hydrate the pivot relationship on the models.
     */
    protected function hydratePivotRelation(array $models)
    {
        // Get all pivot records for these models
        $relatedIds = array_map(static fn ($model) => $model->getKey(), $models);

        $pivotQuery = MongoDBWorkflowRelationship::where($this->foreignPivotKey, $this->parent->getKey())
            ->whereIn($this->relatedPivotKey, $relatedIds);

        // Apply custom pivot where clauses
        foreach ($this->customPivotWheres as $whereArgs) {
            $pivotQuery->where(...$whereArgs);
        }

        $pivots = $pivotQuery->get()
            ->keyBy($this->relatedPivotKey);

        // Attach pivot data to each model
        foreach ($models as $model) {
            $pivot = $pivots->get($model->getKey());
            if ($pivot) {
                // Set pivot as a relation on the model
                $model->setRelation('pivot', $pivot);
            }
        }
    }

    /**
     * Set the where clause for the relation query.
     *
     * @return $this
     */
    protected function setWhere()
    {
        // Already handled in setJoin
        return $this;
    }

    /**
     * Get the pivot columns for the relation.
     *
     * @return array
     */
    protected function aliasedPivotColumns()
    {
        return [];
    }
}
