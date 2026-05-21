<?php

declare(strict_types=1);

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class GenerateWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:400'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $prompt = $this->input('prompt');

        $this->merge([
            'prompt' => is_string($prompt)
                ? Str::of($prompt)
                    ->replaceMatches('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ')
                    ->squish()
                    ->toString()
                : $prompt,
        ]);
    }
}
