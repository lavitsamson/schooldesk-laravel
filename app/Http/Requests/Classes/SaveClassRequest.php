<?php

declare(strict_types=1);

namespace App\Http\Requests\Classes;

use App\Models\SchoolClass;
use App\Rules\SchoolTeacher;
use App\Support\SchoolRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Creating (POST) or updating (PUT /classes/{schoolClass}) a class.
 */
class SaveClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        $class = $this->schoolClass();

        return $this->user()?->can($class === null ? 'create' : 'update', $class ?? SchoolClass::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // A class never moves to another year, so the year is only set on create.
            'academic_year_id' => $this->schoolClass() === null
                ? ['required', 'integer', SchoolRule::exists('academic_years')]
                : ['prohibited'],
            'name' => ['required', 'string', 'max:50'],
            'section' => ['nullable', 'string', 'max:10'],
            'class_teacher_id' => ['nullable', 'integer', new SchoolTeacher],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $class = $this->schoolClass();
                $section = trim($this->string('section')->toString());

                $duplicate = SchoolClass::query()
                    ->where('academic_year_id', $class->academic_year_id ?? $this->integer('academic_year_id'))
                    ->where('name', trim($this->string('name')->toString()))
                    ->when($section === '', fn ($q) => $q->whereNull('section'), fn ($q) => $q->where('section', $section))
                    ->when($class !== null, fn ($q) => $q->whereKeyNot($class?->id))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('name', 'This class already exists in the academic year.');
                }
            },
        ];
    }

    public function schoolClass(): ?SchoolClass
    {
        /** @var SchoolClass|null */
        return $this->route('schoolClass');
    }
}
