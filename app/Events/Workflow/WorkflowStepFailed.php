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

class WorkflowStepFailed implements ShouldBroadcast
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
            'run' => ['id' => $this->run->id, 'status' => $this->run->status?->value],
            'step_run' => $this->stepRun === null ? null : [
                'id' => $this->stepRun->id,
                'step_id' => $this->stepRun->step_id,
                'status' => $this->stepRun->status?->value,
                'error' => $this->stepRun->error,
                'completed_at' => $this->stepRun->completed_at?->toISOString(),
            ],
        ];
    }
}
