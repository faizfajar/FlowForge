<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\StepRunStatus;
use App\Enums\WorkflowRunStatus;
use App\Events\Workflow\WorkflowRunCompleted;
use App\Models\ExecutionLog;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class CancelWorkflowJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $workflowRunId) {}

    public function handle(): void
    {
        $run = WorkflowRun::query()->findOrFail($this->workflowRunId);
        $completedAt = Carbon::now();

        StepRun::query()
            ->where('workflow_run_id', $run->id)
            ->whereIn('status', [StepRunStatus::PENDING, StepRunStatus::RUNNING])
            ->update([
                'status' => StepRunStatus::CANCELLED,
                'error' => 'Workflow run cancelled.',
                'completed_at' => $completedAt,
            ]);

        $run->forceFill([
            'status' => WorkflowRunStatus::CANCELLED,
            'completed_at' => $completedAt,
        ])->save();

        ExecutionLog::query()->create([
            'workflow_run_id' => $run->id,
            'step_run_id' => null,
            'level' => 'warning',
            'message' => 'Workflow run cancelled.',
            'context' => [],
            'logged_at' => $completedAt,
        ]);

        event(new WorkflowRunCompleted($run->refresh()));
    }
}
