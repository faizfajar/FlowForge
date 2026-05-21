<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\StepRunStatus;
use App\Enums\WorkflowRunStatus;
use App\Events\Workflow\WorkflowRunCompleted;
use App\Events\Workflow\WorkflowRunStarted;
use App\Models\ExecutionLog;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use App\Services\Workflow\DagParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(private readonly string $workflowRunId)
    {
        $this->onQueue('high');
    }

    public function handle(DagParser $dagParser): void
    {
        $run = WorkflowRun::query()->with('version')->findOrFail($this->workflowRunId);
        $dag = is_array($run->version?->dag) ? $run->version->dag : [];
        $parsedDag = $dagParser->parse($dag);

        $run->forceFill([
            'status' => WorkflowRunStatus::RUNNING,
            'started_at' => Carbon::now(),
        ])->save();

        event(new WorkflowRunStarted($run->refresh()));
        $this->log($run, 'info', 'Workflow run started.');

        try {
            foreach ($parsedDag->parallelGroups as $group) {
                foreach ($group as $stepId) {
                    ExecuteStepJob::dispatchSync($run->id, $parsedDag->steps[$stepId]);
                }

                $failedStep = StepRun::query()
                    ->where('workflow_run_id', $run->id)
                    ->whereIn('step_id', $group)
                    ->where('status', StepRunStatus::FAILED)
                    ->first();

                if ($failedStep instanceof StepRun && ! (bool) ($parsedDag->steps[$failedStep->step_id]['optional'] ?? false)) {
                    $run->forceFill([
                        'status' => WorkflowRunStatus::FAILED,
                        'completed_at' => Carbon::now(),
                    ])->save();

                    $this->log($run, 'error', 'Workflow run failed.', ['step_id' => $failedStep->step_id]);
                    event(new WorkflowRunCompleted($run->refresh()));

                    return;
                }
            }

            $run->forceFill([
                'status' => WorkflowRunStatus::COMPLETED,
                'completed_at' => Carbon::now(),
            ])->save();

            $this->log($run, 'info', 'Workflow run completed.');
            event(new WorkflowRunCompleted($run->refresh()));
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => WorkflowRunStatus::FAILED,
                'completed_at' => Carbon::now(),
            ])->save();

            $this->log($run, 'error', 'Workflow run failed.', ['error' => $exception->getMessage()]);
            event(new WorkflowRunCompleted($run->refresh()));

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(WorkflowRun $run, string $level, string $message, array $context = []): void
    {
        ExecutionLog::query()->create([
            'workflow_run_id' => $run->id,
            'step_run_id' => null,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'logged_at' => Carbon::now(),
        ]);
    }
}
