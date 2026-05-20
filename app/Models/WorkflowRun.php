<?php

namespace App\Models;

use App\Enums\TriggerType;
use App\Enums\WorkflowRunStatus;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRun extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'workflow_definition_id',
        'workflow_version_id',
        'status',
        'trigger_type',
        'triggered_by',
        'started_at',
        'completed_at',
        'timeout_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkflowRunStatus::class,
            'trigger_type' => TriggerType::class,
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'timeout_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', WorkflowRunStatus::RUNNING);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', WorkflowRunStatus::FAILED);
    }

    public function scopeForWorkflow(Builder $query, string $id): Builder
    {
        return $query->where('workflow_definition_id', $id);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function stepRuns(): HasMany
    {
        return $this->hasMany(StepRun::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
