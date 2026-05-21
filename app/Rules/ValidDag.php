<?php

declare(strict_types=1);

namespace App\Rules;

use App\Exceptions\WorkflowException;
use App\Services\Workflow\DagParser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidDag implements ValidationRule
{
    public function __construct(private readonly DagParser $dagParser)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The :attribute must be a valid DAG object.');

            return;
        }

        try {
            $this->dagParser->parse($value);
        } catch (WorkflowException $exception) {
            $fail($exception->getMessage());
        }
    }
}
