<?php

declare(strict_types=1);

namespace App\Http\Requests\Trigger;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class WebhookTriggerRequest extends FormRequest
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
            'x_signature' => ['required', 'string', 'size:64', 'regex:/^[a-fA-F0-9]{64}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $signature = $this->header('X-Signature');

        $this->merge([
            'x_signature' => is_string($signature)
                ? Str::of($signature)->trim()->toString()
                : $signature,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $content = $this->getContent();

            if ($content === '' || $content === 'null') {
                return;
            }

            json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $validator->errors()->add('payload', 'The webhook payload must be valid JSON.');
            }
        });
    }
}
