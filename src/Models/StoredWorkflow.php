<?php

declare(strict_types=1);

namespace Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\ModelStates\HasStates;
use Workflow\States\WorkflowStatus;
use Workflow\WorkflowStub;

final class StoredWorkflow extends Model
{
    use HasStates;

    /**
     * @var string
     */
    protected $table = 'workflows';

    /**
     * @var mixed[]
     */
    protected $guarded = [];

    /**
     * @var array<string, class-string<\Workflow\States\WorkflowStatus>>
     */
    protected $casts = [
        'status' => WorkflowStatus::class,
    ];

    public function getCreatedAtAttribute(string $value): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s.u', $value);
    }

    public function setCreatedAtAttribute(Carbon $value): void
    {
        $this->attributes['created_at'] = $value->format('Y-m-d H:i:s.u');
    }

    public function getUpdatedAtAttribute(string $value): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s.u', $value);
    }

    public function setUpdatedAtAttribute(Carbon $value): void
    {
        $this->attributes['updated_at'] = now()->format('Y-m-d H:i:s.u');
    }

    public function toWorkflow()
    {
        return WorkflowStub::fromStoredWorkflow($this);
    }

    public function logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StoredWorkflowLog::class);
    }

    public function signals(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StoredWorkflowSignal::class);
    }

    public function timers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StoredWorkflowTimer::class);
    }

    public function exceptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StoredWorkflowException::class);
    }
}
