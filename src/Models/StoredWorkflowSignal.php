<?php

declare(strict_types=1);

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Model;

final class StoredWorkflowSignal extends Model
{
    /**
     * @var string
     */
    protected $table = 'workflow_signals';

    /**
     * @var mixed[]
     */
    protected $guarded = [];

    public function workflow(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StoredWorkflow::class);
    }
}
