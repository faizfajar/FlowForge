<?php

declare(strict_types=1);

namespace App\Events\Workflow;

use App\Models\StepRun;
use App\Models\WorkflowRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowRunStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly WorkflowRun $run, public readonly ?StepRun $stepRun = null)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("tenant.{$this->run->tenant_id}");
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'run' => [
                'id' => $this->run->id,
                'tenant_id' => $this->run->tenant_id,
                'workflow_definition_id' => $this->run->workflow_definition_id,
                'workflow_version_id' => $this->run->workflow_version_id,
                'status' => $this->run->status?->value,
                'trigger_type' => $this->run->trigger_type?->value,
                'started_at' => $this->run->started_at?->toISOString(),
                'completed_at' => $this->run->completed_at?->toISOString(),
            ],
            'step_run' => null,
        ];
    }
}
