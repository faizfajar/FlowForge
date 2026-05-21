<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'min:8', 'confirmed'],
            'tenant_name' => ['required', 'string', 'max:160'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->cleanString($this->input('name')),
            'email' => $this->cleanEmail($this->input('email')),
            'tenant_name' => $this->cleanString($this->input('tenant_name')),
        ]);
    }

    private function cleanString(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return Str::of($value)
            ->replaceMatches('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ')
            ->stripTags()
            ->squish()
            ->toString();
    }

    private function cleanEmail(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return Str::of($value)
            ->replaceMatches('/[\x00-\x1F\x7F]/', '')
            ->trim()
            ->lower()
            ->toString();
    }
}
