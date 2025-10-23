<?php

declare(strict_types=1);

namespace Workflow\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Workflow\Domain\Contracts\RelationshipAdapterInterface;

/**
 * Eloquent/SQL implementation using standard pivot tables.
 */
class EloquentRelationshipAdapter implements RelationshipAdapterInterface
{
    public function createChildrenRelation(
        Model $parent,
        string $relatedClass,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey
    ): BelongsToMany {
        return $parent->belongsToMany(
            $relatedClass,
            $table,
            $foreignPivotKey,
            $relatedPivotKey
        )->withPivot(['parent_index', 'parent_now']);
    }

    public function createParentsRelation(
        Model $parent,
        string $relatedClass,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey
    ): BelongsToMany {
        return $parent->belongsToMany(
            $relatedClass,
            $table,
            $foreignPivotKey,
            $relatedPivotKey
        )->withPivot(['parent_index', 'parent_now']);
    }
}
