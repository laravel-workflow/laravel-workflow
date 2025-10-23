<?php

declare(strict_types=1);

namespace Workflow\Infrastructure\Persistence\MongoDB;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Workflow\Domain\Contracts\RelationshipAdapterInterface;

/**
 * MongoDB relationship adapter.
 *
 * Uses a custom BelongsToMany implementation to work around MongoDB Laravel's
 * limitations with pivot collections.
 */
class MongoDBRelationshipAdapter implements RelationshipAdapterInterface
{
    public function createChildrenRelation(
        Model $parent,
        string $relatedClass,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey
    ): BelongsToMany {
        // Create a new instance of the related model
        $relatedInstance = new $relatedClass();

        $relation = new MongoDBBelongsToManyRelation(
            $relatedInstance->newQuery(),
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parent->getKeyName(),
            $relatedInstance->getKeyName(),
            null
        );

        // Add pivot attributes
        return $relation->withPivot(['parent_index', 'parent_now']);
    }

    public function createParentsRelation(
        Model $parent,
        string $relatedClass,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey
    ): BelongsToMany {
        // Create a new instance of the related model
        $relatedInstance = new $relatedClass();

        $relation = new MongoDBBelongsToManyRelation(
            $relatedInstance->newQuery(),
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parent->getKeyName(),
            $relatedInstance->getKeyName(),
            null
        );

        // Add pivot attributes
        return $relation->withPivot(['parent_index', 'parent_now']);
    }
}
