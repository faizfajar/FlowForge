<?php

declare(strict_types=1);

namespace App\Services\Workflow\Executors;

use App\Exceptions\StepExecutionException;
use App\Models\StepRun;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Throwable;

class ScriptExecutor implements StepExecutorInterface
{
    public function __construct(private readonly ExpressionLanguage $expressionLanguage) {}

    public function execute(StepRun $stepRun, array $previousOutputs): array
    {
        $config = is_array($stepRun->input) ? $stepRun->input : [];
        $expression = $config['expression'] ?? null;

        if (! is_string($expression) || $expression === '') {
            throw new StepExecutionException('SCRIPT step requires an expression.');
        }

        // MVP limitation: script steps are constrained to Symfony ExpressionLanguage for deterministic safe evaluation.
        try {
            $result = $this->expressionLanguage->evaluate($expression, $previousOutputs);
        } catch (Throwable $exception) {
            throw new StepExecutionException($exception->getMessage(), previous: $exception);
        }

        return [
            'result' => $result,
        ];
    }
}
