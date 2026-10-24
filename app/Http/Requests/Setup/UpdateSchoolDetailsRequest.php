<?php

declare(strict_types=1);

namespace App\Http\Requests\Setup;

use App\Enums\Locale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateSchoolDetailsRequest extends SetupStepRequest
{
    public const CURRENCIES = ['EUR', 'CHF', 'GBP', 'USD'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2', 'alpha:ascii', 'uppercase'],
            'timezone' => ['required', 'timezone:all'],
            'locale' => ['required', Rule::enum(Locale::class)],
            'currency' => ['required', Rule::in(self::CURRENCIES)],
        ];
    }
}
