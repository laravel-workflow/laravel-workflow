<?php

declare(strict_types=1);

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Model;

final class StoredWorkflowTimer extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var string
     */
    protected $table = 'workflow_timers';

    /**
     * @var mixed[]
     */
    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * @var array<string, class-string<\datetime>>
     */
    protected $casts = [
        'stop_at' => 'datetime:Y-m-d H:i:s.u',
    ];

    public function workflow(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StoredWorkflow::class);
    }
}
