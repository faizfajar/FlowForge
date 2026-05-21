<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\StepRunStatus;
use App\Enums\WorkflowRunStatus;
use App\Events\Workflow\WorkflowRunCompleted;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class CancelWorkflowJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $workflowRunId)
    {
    }

    public function handle(): void
    {
        $run = WorkflowRun::query()->findOrFail($this->workflowRunId);

        StepRun::query()
            ->where('workflow_run_id', $run->id)
            ->where('status', StepRunStatus::PENDING)
            ->update([
                'status' => StepRunStatus::CANCELLED,
                'completed_at' => Carbon::now(),
            ]);

        $run->forceFill([
            'status' => WorkflowRunStatus::CANCELLED,
            'completed_at' => Carbon::now(),
        ])->save();

        event(new WorkflowRunCompleted($run->refresh()));
    }
}
