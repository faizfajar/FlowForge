<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionLog extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'workflow_run_id',
        'step_run_id',
        'level',
        'message',
        'context',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'logged_at' => 'immutable_datetime',
        ];
    }

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    public function stepRun(): BelongsTo
    {
        return $this->belongsTo(StepRun::class);
    }
}
