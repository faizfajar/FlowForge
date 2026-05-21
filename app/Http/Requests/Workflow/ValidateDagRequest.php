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
        ];
    }
}
