<?php

namespace App\Models;

use App\Enums\StepRunStatus;
use App\Enums\StepType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StepRun extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workflow_run_id',
        'step_id',
        'step_type',
        'status',
        'input',
        'output',
        'error',
        'attempt',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'step_type' => StepType::class,
            'status' => StepRunStatus::class,
            'input' => 'array',
            'output' => 'array',
            'attempt' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }
}
