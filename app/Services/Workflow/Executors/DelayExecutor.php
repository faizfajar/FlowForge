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

        $existingOutput = is_array($stepRun->output) ? $stepRun->output : [];

        if (($existingOutput['delay_released_once'] ?? false) === true) {
            return [
                'delayed_until' => (string) ($existingOutput['delayed_until'] ?? Carbon::now()->timezone(config('app.timezone'))->toIso8601String()),
            ];
        }

        return [
            '__release_after' => $seconds,
            'delay_released_once' => true,
            'delayed_until' => Carbon::now()->addSeconds($seconds)->timezone(config('app.timezone'))->toIso8601String(),
        ];
    }
}
