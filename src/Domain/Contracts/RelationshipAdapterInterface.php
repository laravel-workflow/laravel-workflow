<?php

declare(strict_types=1);

namespace Workflow\Domain\Contracts;

/**
 * Interface for handling database-specific relationship operations.
 *
 * Different database backends (SQL with pivot tables, MongoDB with pivot collections)
 * implement this interface to provide consistent relationship behavior.
 */
interface RelationshipAdapterInterface
{
    /**
     * Create a BelongsToMany relationship for children.
     */
    public function createChildrenRelation(
        \Illuminate\Database\Eloquent\Model $parent,
        string $relatedClass,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey
    ): \Illuminate\Database\Eloquent\Relations\BelongsToMany;

    /**
     * Create a BelongsToMany relationship for parents.
     */
    public function createParentsRelation(
        \Illuminate\Database\Eloquent\Model $parent,
        string $relatedClass,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey
    ): \Illuminate\Database\Eloquent\Relations\BelongsToMany;
}
