<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\WorkflowRunStatus;
use App\Events\Workflow\WorkflowRunCompleted;
use App\Events\Workflow\WorkflowRunStarted;
use App\Models\ExecutionLog;
use App\Models\WorkflowRun;
use App\Services\Workflow\DagParser;
use App\Services\Workflow\DTOs\ParsedDag;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Throwable;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(
        private readonly string $workflowRunId,
        private readonly int $waveIndex = 0,
    ) {
        $this->onQueue('high');
    }

    public function handle(DagParser $dagParser): void
    {
        $run = WorkflowRun::query()
            ->with(['definition', 'version'])
            ->findOrFail($this->workflowRunId);

        if ($this->isTerminal($run)) {
            return;
        }

        if ($this->isTimedOut($run)) {
            $this->markTimedOut($run);

            return;
        }

        if ($run->status === WorkflowRunStatus::PENDING) {
            $run->forceFill([
                'status' => WorkflowRunStatus::RUNNING,
                'started_at' => $run->started_at ?? Carbon::now(),
            ])->save();

            $this->log($run, 'info', 'Workflow started.');
            event(new WorkflowRunStarted($run->refresh()->loadMissing('definition')));
        }

        $parsedDag = $this->parseDag($dagParser, $run);
        $wave = $parsedDag->parallelGroups[$this->waveIndex] ?? null;

        if ($wave === null) {
            $this->completeRun($run);

            return;
        }

        $jobs = array_map(
            fn (string $stepId): ExecuteStepJob => new ExecuteStepJob($run->id, $parsedDag->steps[$stepId]),
            $wave
        );

        $nextWaveIndex = $this->waveIndex + 1;

        Bus::batch($jobs)
            ->name("workflow-run:{$run->id}:wave:{$this->waveIndex}")
            ->onQueue('high')
            ->then(static function (Batch $batch) use ($run, $nextWaveIndex): void {
                self::dispatchNextWave($run->id, $nextWaveIndex);
            })
            ->catch(static function (Batch $batch, Throwable $exception) use ($run): void {
                self::failRunFromBatch($run->id, $exception->getMessage());
            })
            ->dispatch();
    }

    public static function dispatchNextWave(string $workflowRunId, int $waveIndex): void
    {
        $run = WorkflowRun::query()->find($workflowRunId);

        if (! $run instanceof WorkflowRun) {
            return;
        }

        if (in_array($run->status, [WorkflowRunStatus::FAILED, WorkflowRunStatus::COMPLETED, WorkflowRunStatus::CANCELLED], true)) {
            return;
        }

        if ($run->timeout_at !== null && $run->timeout_at->isPast() && $run->status !== WorkflowRunStatus::CANCELLED) {
            self::markTimedOutStatic($run);

            return;
        }

        self::dispatch($workflowRunId, $waveIndex)->onQueue('high');
    }

    public static function failRunFromBatch(string $workflowRunId, string $error): void
    {
        $run = WorkflowRun::query()
            ->with('definition')
            ->find($workflowRunId);

        if (! $run instanceof WorkflowRun || in_array($run->status, [WorkflowRunStatus::FAILED, WorkflowRunStatus::COMPLETED, WorkflowRunStatus::CANCELLED], true)) {
            return;
        }

        $run->forceFill([
            'status' => WorkflowRunStatus::FAILED,
            'completed_at' => Carbon::now(),
        ])->save();

        ExecutionLog::query()->create([
            'workflow_run_id' => $run->id,
            'step_run_id' => null,
            'level' => 'error',
            'message' => 'Workflow failed.',
            'context' => ['error' => $error],
            'logged_at' => Carbon::now(),
        ]);

        event(new WorkflowRunCompleted($run->refresh()->loadMissing('definition')));
    }

    private static function markTimedOutStatic(WorkflowRun $run): void
    {
        if (in_array($run->status, [WorkflowRunStatus::FAILED, WorkflowRunStatus::COMPLETED, WorkflowRunStatus::CANCELLED], true)) {
            return;
        }

        $run->forceFill([
            'status' => WorkflowRunStatus::FAILED,
            'completed_at' => Carbon::now(),
        ])->save();

        ExecutionLog::query()->create([
            'workflow_run_id' => $run->id,
            'step_run_id' => null,
            'level' => 'error',
            'message' => 'Workflow run exceeded the global timeout.',
            'context' => [],
            'logged_at' => Carbon::now(),
        ]);

        event(new WorkflowRunCompleted($run->refresh()->loadMissing('definition')));
    }

    private function parseDag(DagParser $dagParser, WorkflowRun $run): ParsedDag
    {
        $dag = $run->version?->dag;

        return $dagParser->parse(is_array($dag) ? $dag : []);
    }

    private function completeRun(WorkflowRun $run): void
    {
        $fresh = $run->fresh(['definition']);

        if (! $fresh instanceof WorkflowRun || $this->isTerminal($fresh)) {
            return;
        }

        $fresh->forceFill([
            'status' => WorkflowRunStatus::COMPLETED,
            'completed_at' => Carbon::now(),
        ])->save();

        $this->log($fresh, 'info', 'Workflow completed.');
        event(new WorkflowRunCompleted($fresh->refresh()->loadMissing('definition')));
    }

    private function markTimedOut(WorkflowRun $run): void
    {
        self::markTimedOutStatic($run->fresh(['definition']) ?? $run);
    }

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

    private function isTerminal(WorkflowRun $run): bool
    {
        return in_array($run->status, [WorkflowRunStatus::FAILED, WorkflowRunStatus::COMPLETED, WorkflowRunStatus::CANCELLED], true);
    }

    private function isTimedOut(WorkflowRun $run): bool
    {
        return $run->timeout_at !== null
            && $run->timeout_at->isPast()
            && $run->status !== WorkflowRunStatus::CANCELLED;
    }
}
