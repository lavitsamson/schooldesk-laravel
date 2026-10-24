<?php

declare(strict_types=1);

namespace App\Http\Requests\Setup;

use Illuminate\Contracts\Validation\ValidationRule;

class SyncSubjectsRequest extends SetupStepRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subjects' => ['required', 'array', 'min:1', 'max:100'],
            'subjects.*.name' => ['required', 'string', 'max:100'],
            'subjects.*.code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', 'distinct:ignore_case'],
        ];
    }
}
