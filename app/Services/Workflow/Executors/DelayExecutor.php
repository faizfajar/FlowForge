<?php

declare(strict_types=1);

namespace App\Services\Workflow\Executors;

use App\Enums\WorkflowRunStatus;
use App\Exceptions\StepExecutionException;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use Illuminate\Support\Carbon;

class DelayExecutor implements StepExecutorInterface
{
    private const POLL_MICROSECONDS = 100_000;

    public function execute(StepRun $stepRun, array $previousOutputs): array
    {
        $config = is_array($stepRun->input) ? $stepRun->input : [];
        $seconds = (int) ($config['seconds'] ?? 0);

        if ($seconds < 1) {
            throw new StepExecutionException('DELAY step requires seconds greater than zero.');
        }

        $delayedUntil = Carbon::now()->addSeconds($seconds);
        $remainingMicroseconds = $seconds * 1_000_000;

        while ($remainingMicroseconds > 0) {
            if ($this->shouldStop($stepRun->workflow_run_id)) {
                throw new StepExecutionException('Workflow run cancelled.');
            }

            $sleepFor = min(self::POLL_MICROSECONDS, $remainingMicroseconds);
            usleep($sleepFor);
            $remainingMicroseconds -= $sleepFor;
        }

        return [
            'delayed_until' => $delayedUntil->timezone(config('app.timezone'))->toIso8601String(),
        ];
    }

    private function shouldStop(string $workflowRunId): bool
    {
        $run = WorkflowRun::query()->find($workflowRunId);

        if (! $run instanceof WorkflowRun) {
            return true;
        }

        if ($run->status === WorkflowRunStatus::CANCELLED) {
            return true;
        }

        return $run->timeout_at !== null && $run->timeout_at->isPast();
    }
}
