<?php

declare(strict_types=1);

namespace App\Http\Requests\Gdpr;

use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /students/{student}/anonymise. Erasure cannot be undone, so the admin confirms it by
 * typing the student's admission number.
 */
class AnonymiseStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('anonymise', $this->student()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', Rule::in([$this->student()->admission_no])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirmation.in' => __('Type the admission number exactly as shown.')];
    }

    public function student(): Student
    {
        /** @var Student */
        return $this->route('student');
    }
}
