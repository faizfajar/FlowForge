<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\StepRunStatus;
use App\Enums\StepType;
use App\Events\Workflow\WorkflowStepCompleted;
use App\Events\Workflow\WorkflowStepFailed;
use App\Events\Workflow\WorkflowStepStarted;
use App\Models\ExecutionLog;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use App\Services\Workflow\ExecutorFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Throwable;

class ExecuteStepJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @param  array<string, mixed>  $step
     */
    public function __construct(private readonly string $workflowRunId, private readonly array $step)
    {
        $this->onQueue('high');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 2, 4];
    }

    public function handle(ExecutorFactory $executorFactory): void
    {
        $run = WorkflowRun::query()->with('version')->findOrFail($this->workflowRunId);
        $stepId = (string) $this->step['id'];
        $stepType = StepType::from((string) $this->step['type']);
        $config = is_array($this->step['config'] ?? null) ? $this->step['config'] : [];

        if ($this->isCancelled($run)) {
            return;
        }

        $stepRun = StepRun::query()->firstOrNew([
            'workflow_run_id' => $run->id,
            'step_id' => $stepId,
        ]);

        if ($stepRun->exists && $stepRun->status === StepRunStatus::CANCELLED) {
            return;
        }

        $stepRun->fill([
            'step_type' => $stepType,
            'status' => StepRunStatus::RUNNING,
            'input' => $config,
            'attempt' => max(1, (int) $stepRun->attempt + ($stepRun->exists ? 1 : 0)),
            'started_at' => Carbon::now(),
            'completed_at' => null,
            'error' => null,
        ])->save();

        event(new WorkflowStepStarted($run, $stepRun));
        $this->log($run, $stepRun, 'info', 'Step started.');

        try {
            $output = $executorFactory->make($stepType)->execute($stepRun, $this->previousOutputs($run));

            if ($this->isCancelled($run)) {
                $this->markCancelled($stepRun);
                $this->log($run, $stepRun, 'warning', 'Step cancelled.');

                return;
            }

            $stepRun->forceFill([
                'status' => StepRunStatus::SUCCESS,
                'output' => $output,
                'completed_at' => Carbon::now(),
            ])->save();

            $this->log($run, $stepRun, 'info', 'Step completed.', ['output' => $output]);
            event(new WorkflowStepCompleted($run->refresh(), $stepRun->refresh()));
        } catch (Throwable $exception) {
            if ($this->isCancelled($run)) {
                $this->markCancelled($stepRun);
                $this->log($run, $stepRun, 'warning', 'Step cancelled.', ['error' => $exception->getMessage()]);

                return;
            }

            $hasRetriesRemaining = $this->attempts() < $this->tries;

            if ($hasRetriesRemaining) {
                $stepRun->forceFill([
                    'status' => StepRunStatus::RUNNING,
                    'error' => $exception->getMessage(),
                    'completed_at' => null,
                ])->save();

                $this->log(
                    $run,
                    $stepRun,
                    'warning',
                    sprintf('Step failed, retrying attempt %d/%d.', $this->attempts(), $this->tries),
                    ['error' => $exception->getMessage()]
                );

                throw $exception;
            }

            $stepRun->forceFill([
                'status' => StepRunStatus::FAILED,
                'error' => $exception->getMessage(),
                'completed_at' => Carbon::now(),
            ])->save();

            $this->log($run, $stepRun, 'error', 'Step failed.', ['error' => $exception->getMessage()]);
            event(new WorkflowStepFailed($run->refresh(), $stepRun->refresh()));

            throw $exception;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function previousOutputs(WorkflowRun $run): array
    {
        return StepRun::query()
            ->where('workflow_run_id', $run->id)
            ->where('status', StepRunStatus::SUCCESS)
            ->whereNotNull('output')
            ->pluck('output', 'step_id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(WorkflowRun $run, StepRun $stepRun, string $level, string $message, array $context = []): void
    {
        ExecutionLog::query()->create([
            'workflow_run_id' => $run->id,
            'step_run_id' => $stepRun->id,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'logged_at' => Carbon::now(),
        ]);
    }

    private function markCancelled(StepRun $stepRun): void
    {
        $stepRun->forceFill([
            'status' => StepRunStatus::CANCELLED,
            'error' => 'Workflow run cancelled.',
            'completed_at' => Carbon::now(),
        ])->save();
    }

    private function isCancelled(WorkflowRun $run): bool
    {
        return $run->fresh()?->status === \App\Enums\WorkflowRunStatus::CANCELLED;
    }
}
