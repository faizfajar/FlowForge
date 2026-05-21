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

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->run->tenant_id}.workflows"),
            new PrivateChannel("tenant.{$this->run->tenant_id}.workflow.{$this->run->workflow_definition_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'WorkflowRunStarted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'run' => $this->runPayload(),
            'workflow' => [
                'id' => $this->run->workflow_definition_id,
                'last_run' => $this->runPayload(),
            ],
            'step_run' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runPayload(): array
    {
        return [
                'id' => $this->run->id,
                'workflow' => [
                    'id' => $this->run->workflow_definition_id,
                    'name' => $this->run->definition?->name ?? 'Workflow',
                ],
                'tenant_id' => $this->run->tenant_id,
                'workflow_definition_id' => $this->run->workflow_definition_id,
                'workflow_version_id' => $this->run->workflow_version_id,
                'status' => $this->run->status?->value,
                'trigger_type' => $this->run->trigger_type?->value,
                'started_at' => $this->run->started_at?->timezone(config('app.timezone'))->toIso8601String(),
                'completed_at' => $this->run->completed_at?->timezone(config('app.timezone'))->toIso8601String(),
                'step_runs' => [],
        ];
    }
}
