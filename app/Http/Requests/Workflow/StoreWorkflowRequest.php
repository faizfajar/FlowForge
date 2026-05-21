<?php

declare(strict_types=1);

namespace App\Http\Requests\Workflow;

use App\Rules\ValidDag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreWorkflowRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'schedule_cron' => ['nullable', 'string', 'max:64', 'regex:/^([*0-9,\-\/]+\s+){4,5}[*0-9,\-\/]+$/'],
            'dag' => ['required', 'array', $validDag],
            'dag.steps' => ['required', 'array', 'min:1', 'max:20'],
            'dag.steps.*.id' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            'dag.steps.*.type' => ['required', 'string', 'in:HTTP_CALL,SCRIPT,DELAY,CONDITION'],
            'dag.steps.*.name' => ['required', 'string', 'max:160'],
            'dag.steps.*.config' => ['required', 'array'],
            'dag.steps.*.dependencies' => ['present', 'array', 'max:20'],
            'dag.steps.*.dependencies.*' => ['string', 'max:80', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->cleanNullableString($this->input('name')),
            'description' => $this->cleanNullableString($this->input('description')),
            'schedule_cron' => $this->cleanNullableString($this->input('schedule_cron')),
        ]);
    }

    private function cleanNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $cleaned = Str::of($value)
            ->replaceMatches('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '')
            ->stripTags()
            ->trim()
            ->toString();

        return $cleaned === '' ? null : $cleaned;
    }
}
