<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'schedule_cron' => $this->schedule_cron,
            'last_scheduled_run_at' => $this->last_scheduled_run_at?->timezone(config('app.timezone'))->toIso8601String(),
            'active_version' => $this->whenLoaded('activeVersion', fn () => new WorkflowVersionResource($this->activeVersion)),
            'last_run' => $this->whenLoaded('runs', fn () => $this->runs->first() === null ? null : new WorkflowRunResource($this->runs->first())),
            'created_at' => $this->created_at?->timezone(config('app.timezone'))->toIso8601String(),
            'updated_at' => $this->updated_at?->timezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
