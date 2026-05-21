<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StepRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'step_id' => $this->step_id,
            'step_type' => $this->step_type?->value,
            'status' => $this->status?->value,
            'input' => $this->input,
            'output' => $this->output,
            'error' => $this->error,
            'attempt' => $this->attempt,
            'started_at' => $this->started_at?->timezone(config('app.timezone'))->toIso8601String(),
            'completed_at' => $this->completed_at?->timezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
