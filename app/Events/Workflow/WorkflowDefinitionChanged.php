<?php

declare(strict_types=1);

namespace App\Events\Workflow;

use App\Models\WorkflowDefinition;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowDefinitionChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $action,
        public readonly string $workflowId,
        public readonly ?WorkflowDefinition $workflow = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("tenant.{$this->tenantId}.workflows");
    }

    public function broadcastAs(): string
    {
        return 'WorkflowDefinitionChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'workflow_id' => $this->workflowId,
            'workflow' => $this->workflow === null ? null : [
                'id' => $this->workflow->id,
                'name' => $this->workflow->name,
                'description' => $this->workflow->description,
                'schedule_cron' => $this->workflow->schedule_cron,
                'last_scheduled_run_at' => $this->workflow->last_scheduled_run_at?->timezone(config('app.timezone'))->toIso8601String(),
                'active_version' => $this->workflow->activeVersion === null ? null : [
                    'id' => $this->workflow->activeVersion->id,
                    'version_number' => $this->workflow->activeVersion->version_number,
                    'dag' => $this->workflow->activeVersion->dag,
                    'is_active' => $this->workflow->activeVersion->is_active,
                    'created_at' => $this->workflow->activeVersion->created_at?->timezone(config('app.timezone'))->toIso8601String(),
                ],
                'last_run' => null,
                'created_at' => $this->workflow->created_at?->timezone(config('app.timezone'))->toIso8601String(),
                'updated_at' => $this->workflow->updated_at?->timezone(config('app.timezone'))->toIso8601String(),
            ],
        ];
    }
}
