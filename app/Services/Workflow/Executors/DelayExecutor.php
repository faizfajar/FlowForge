<?php

declare(strict_types=1);

namespace App\Services\Workflow\Executors;

use App\Exceptions\StepExecutionException;
use App\Models\StepRun;
use Illuminate\Support\Carbon;

class DelayExecutor implements StepExecutorInterface
{
    public function execute(StepRun $stepRun, array $previousOutputs): array
    {
        $config = is_array($stepRun->input) ? $stepRun->input : [];
        $seconds = (int) ($config['seconds'] ?? 0);

        if ($seconds < 1) {
            throw new StepExecutionException('DELAY step requires seconds greater than zero.');
        }

        $delayedUntil = Carbon::now()->addSeconds($seconds);
        sleep($seconds);

        return [
            'delayed_until' => $delayedUntil->timezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
