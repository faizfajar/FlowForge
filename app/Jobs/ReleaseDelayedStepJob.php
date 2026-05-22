<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExecutionLog;
use App\Models\StepRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class ReleaseDelayedStepJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $stepRunId) {}

    public function handle(): void
    {
        $stepRun = StepRun::query()->find($this->stepRunId);

        if (! $stepRun instanceof StepRun) {
            return;
        }

        ExecutionLog::query()->create([
            'workflow_run_id' => $stepRun->workflow_run_id,
            'step_run_id' => $stepRun->id,
            'level' => 'info',
            'message' => 'Delay window completed.',
            'context' => [],
            'logged_at' => Carbon::now(),
        ]);
    }
}
