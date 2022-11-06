<?php

declare(strict_types=1);

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Model;

final class StoredWorkflowTimer extends Model
{
    /**
     * @var string
     */
    protected $table = 'workflow_timers';

    /**
     * @var mixed[]
     */
    protected $guarded = [];

    /**
     * @var array<string, class-string<\datetime>>
     */
    protected $casts = [
        'stop_at' => 'datetime',
    ];

    public function workflow(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StoredWorkflow::class);
    }
}
