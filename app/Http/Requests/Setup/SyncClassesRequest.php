<?php

declare(strict_types=1);

namespace App\Http\Requests\Setup;

use App\Models\AcademicYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class SyncClassesRequest extends SetupStepRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'classes' => ['required', 'array', 'min:1', 'max:100'],
            'classes.*.name' => ['required', 'string', 'max:50'],
            'classes.*.section' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! AcademicYear::query()->where('is_current', true)->exists()) {
                    $validator->errors()->add('classes', __('Add the academic year before adding classes.'));
                }

                /** @var list<array{name?: string, section?: string|null}> $classes */
                $classes = (array) $this->input('classes', []);
                $seen = [];

                foreach ($classes as $index => $class) {
                    $key = Str::lower(trim(($class['name'] ?? '').' '.($class['section'] ?? '')));

                    if (isset($seen[$key])) {
                        $validator->errors()->add("classes.{$index}.name", __('This class is listed twice.'));
                    }

                    $seen[$key] = true;
                }
            },
        ];
    }
}
