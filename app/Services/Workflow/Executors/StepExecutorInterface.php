<?php

declare(strict_types=1);

namespace App\Services\Workflow\Executors;

use App\Models\StepRun;

interface StepExecutorInterface
{
    /**
     * @param  array<string, array<string, mixed>>  $previousOutputs
     * @return array<string, mixed>
     */
    public function execute(StepRun $stepRun, array $previousOutputs): array;
}
