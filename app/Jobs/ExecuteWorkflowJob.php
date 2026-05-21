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

    private const WAVE_POLL_MICROSECONDS = 200_000;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(private readonly string $workflowRunId)
    {
        $this->onQueue('high');
    }

    public function handle(DagParser $dagParser): void
    {
        $run = WorkflowRun::query()->with('version')->findOrFail($this->workflowRunId);

        if ($this->isCancelled($run)) {
            return;
        }

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
                if ($this->isCancelled($run)) {
                    return;
                }

                foreach ($group as $stepId) {
                    ExecuteStepJob::dispatch($run->id, $parsedDag->steps[$stepId])->onQueue('high');
                }

                if (! $this->waitForWave($run, $group)) {
                    return;
                }

                $failedStep = $this->failedStepInWave($run, $group);

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
            if ($this->isCancelled($run)) {
                return;
            }

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
     * @param  array<int, string>  $stepIds
     */
    private function waitForWave(WorkflowRun $run, array $stepIds): bool
    {
        while (true) {
            if ($this->isCancelled($run)) {
                return false;
            }

            $completed = StepRun::query()
                ->where('workflow_run_id', $run->id)
                ->whereIn('step_id', $stepIds)
                ->whereIn('status', [
                    StepRunStatus::SUCCESS,
                    StepRunStatus::FAILED,
                    StepRunStatus::SKIPPED,
                    StepRunStatus::CANCELLED,
                ])
                ->count();

            if ($completed >= count($stepIds)) {
                return true;
            }

            usleep(self::WAVE_POLL_MICROSECONDS);
        }
    }

    /**
     * @param  array<int, string>  $stepIds
     */
    private function failedStepInWave(WorkflowRun $run, array $stepIds): ?StepRun
    {
        return StepRun::query()
            ->where('workflow_run_id', $run->id)
            ->whereIn('step_id', $stepIds)
            ->where('status', StepRunStatus::FAILED)
            ->first();
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

    private function isCancelled(WorkflowRun $run): bool
    {
        return $run->fresh()?->status === WorkflowRunStatus::CANCELLED;
    }
}
