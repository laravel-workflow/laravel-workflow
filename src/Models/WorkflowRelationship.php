<?php

declare(strict_types=1);

namespace Workflow\Models;

/**
 * @extends Illuminate\Database\Eloquent\Model
 * 
 * This model represents the pivot table for workflow parent-child relationships.
 * For MongoDB, this acts as a separate collection to store relationship data with pivot attributes.
 */
class WorkflowRelationship extends Model
{
    public const UPDATED_AT = null;
    public const CREATED_AT = null;

    /**
     * @var string
     */
    protected $table = 'workflow_relationships';

    /**
     * @var mixed[]
     */
    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @var array<string, class-string<\datetime>>
     */
    protected $casts = [
        'parent_now' => 'datetime:Y-m-d H:i:s.u',
    ];
}
