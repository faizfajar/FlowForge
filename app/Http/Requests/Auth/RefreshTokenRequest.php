<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class RefreshTokenRequest extends FormRequest
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
            'refresh_token' => ['nullable', 'string', 'max:4096'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $refreshToken = $this->input('refresh_token');

        $this->merge([
            'refresh_token' => is_string($refreshToken)
                ? Str::of($refreshToken)->trim()->toString()
                : $refreshToken,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $refreshToken = $this->input('refresh_token');

            if ((is_string($refreshToken) && $refreshToken !== '') || is_string($this->bearerToken())) {
                return;
            }

            $validator->errors()->add('refresh_token', 'A refresh token is required.');
        });
    }
}
