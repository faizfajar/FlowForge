<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflow;

use App\Rules\ValidDag;
use Illuminate\Foundation\Http\FormRequest;

class ValidateDagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(ValidDag $validDag): array
    {
        return [
            'dag' => ['required', 'array', $validDag],
            'dag.steps' => ['required', 'array', 'min:1', 'max:20'],
            'dag.steps.*.id' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'dag.steps.*.type' => ['required', 'string', 'in:HTTP_CALL,SCRIPT,DELAY,CONDITION'],
            'dag.steps.*.name' => ['required', 'string', 'max:160'],
            'dag.steps.*.config' => ['required', 'array'],
            'dag.steps.*.dependencies' => ['present', 'array', 'max:20'],
            'dag.steps.*.dependencies.*' => ['string', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
        ];
    }
}
