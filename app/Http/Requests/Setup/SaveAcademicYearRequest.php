<?php

declare(strict_types=1);

namespace App\Http\Requests\Setup;

use App\Support\AcademicYearRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

class SaveAcademicYearRequest extends SetupStepRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return AcademicYearRules::rules();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => AcademicYearRules::checkTermOrder($validator, $this->input('terms')),
        ];
    }
}
