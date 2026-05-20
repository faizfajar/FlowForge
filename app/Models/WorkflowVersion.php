<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowVersion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workflow_definition_id',
        'version_number',
        'dag',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'dag' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
