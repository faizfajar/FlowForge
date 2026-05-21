<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Enums\StepType;
use App\Exceptions\UnsupportedStepTypeException;
use App\Services\Workflow\Executors\ConditionExecutor;
use App\Services\Workflow\Executors\DelayExecutor;
use App\Services\Workflow\Executors\HttpCallExecutor;
use App\Services\Workflow\Executors\ScriptExecutor;
use App\Services\Workflow\Executors\StepExecutorInterface;

class ExecutorFactory
{
    public function __construct(
        private readonly HttpCallExecutor $httpCallExecutor,
        private readonly ConditionExecutor $conditionExecutor,
        private readonly DelayExecutor $delayExecutor,
        private readonly ScriptExecutor $scriptExecutor,
    ) {
    }

    public function make(StepType $type): StepExecutorInterface
    {
        return match ($type) {
            StepType::HTTP_CALL => $this->httpCallExecutor,
            StepType::CONDITION => $this->conditionExecutor,
            StepType::DELAY => $this->delayExecutor,
            StepType::SCRIPT => $this->scriptExecutor,
            default => throw new UnsupportedStepTypeException("Unsupported step type [{$type->value}]."),
        };
    }
}
