<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow' => $this->whenLoaded('definition', fn (): array => [
                'id' => $this->definition->id,
                'name' => $this->definition->name,
            ]),
            'version_number' => $this->whenLoaded('version', fn (): int => (int) $this->version->version_number),
            'dag' => $this->whenLoaded('version', fn (): mixed => $this->version->dag),
            'status' => $this->status?->value,
            'trigger_type' => $this->trigger_type?->value,
            'started_at' => $this->started_at?->timezone(config('app.timezone'))->toIso8601String(),
            'completed_at' => $this->completed_at?->timezone(config('app.timezone'))->toIso8601String(),
            'duration_seconds' => $this->started_at === null || $this->completed_at === null
                ? null
                : (int) $this->started_at->diffInSeconds($this->completed_at),
            'step_runs' => $this->whenLoaded('stepRuns', fn () => StepRunResource::collection($this->stepRuns)),
        ];
    }
}
