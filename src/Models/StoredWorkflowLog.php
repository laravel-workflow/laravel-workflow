<?php

declare(strict_types=1);

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Model;

final class StoredWorkflowLog extends Model
{
    /**
     * @var string
     */
    protected $table = 'workflow_logs';

    /**
     * @var mixed[]
     */
    protected $guarded = [];

    public function workflow(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StoredWorkflow::class);
    }
}
