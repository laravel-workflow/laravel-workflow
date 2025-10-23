# MongoDB Infrastructure Layer

This directory contains MongoDB-specific implementations that work around limitations in the `mongodb/laravel-mongodb` package.

## The Workaround: MongoDBBelongsToManyRelation

### Why This Exists

MongoDB Laravel's `BelongsToMany` implementation has significant limitations:

1. **No Real Pivot Support**: It tries to use embedded arrays instead of pivot collections
2. **No wherePivot()**: Can't filter on pivot attributes like `parent_index` or `parent_now`
3. **No withPivot()**: Can't properly retrieve custom pivot attributes
4. **No Join Support**: MongoDB doesn't support SQL-style joins

### What We Need

Laravel Workflow requires:
- Parent-child workflow relationships with pivot attributes (`parent_index`, `parent_now`)
- Ability to query by pivot attributes (e.g., "find continued workflows where parent_index = PHP_INT_MAX")
- Multiple relationships between the same entities (same parent/child, different indices)

### The Solution

`MongoDBBelongsToManyRelation` is a custom `BelongsToMany` implementation that:

1. **Uses a Real Pivot Collection**: `workflow_relationships` stores the relationships
2. **Implements wherePivot()**: Filters by pivot attributes before fetching related models
3. **Implements withPivot()**: Attaches pivot data to retrieved models
4. **Works Without Joins**: Manually queries the pivot collection and uses `whereIn()` for related IDs

### How It Works

```php
// User code (via adapter):
$workflow->children()->wherePivot('parent_index', PHP_INT_MAX)->get();

// What happens internally:
// 1. Query workflow_relationships where parent_workflow_id = X AND parent_index = PHP_INT_MAX
// 2. Extract child_workflow_id values
// 3. Query workflows where _id IN [extracted IDs]
// 4. Attach pivot data to each model
```

### Future

Ideally, `mongodb/laravel-mongodb` would fix this upstream. Until then, we're stuck with this workaround.

**This is hidden from users** - they interact via the `RelationshipAdapterInterface`, which abstracts this implementation detail.

## Files

- **MongoDBBelongsToManyRelation.php** - The workaround class
- **MongoDBRelationshipAdapter.php** - Adapter that uses the workaround
- **MongoDBDateTimeAdapter.php** - DateTime handling (MongoDB stores as strings for microseconds)
- **MongoDBExceptionHandler.php** - Exception detection (MongoDB throws different exceptions)
- **MongoDBQueryAdapter.php** - Query operations (signal filtering)
- **MongoDBWorkflowRepository.php** - Repository operations (pruning logic)

## Testing

This directory should have comprehensive tests to ensure the workaround maintains parity with Eloquent's native `BelongsToMany`.
