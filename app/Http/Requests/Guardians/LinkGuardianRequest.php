<?php

declare(strict_types=1);

namespace App\Http\Requests\Guardians;

use App\Models\Guardian;
use App\Models\Student;
use App\Support\SchoolRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Adds a guardian to a student: either a new guardian, or an existing one of this school found
 * by email (siblings share their guardians).
 */
class LinkGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->student()) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['mode' => ['required', Rule::in(['new', 'existing'])], 'is_primary' => ['boolean']];

        if ($this->input('mode') === 'existing') {
            return [...$rules, 'email' => ['required', 'string', 'email', SchoolRule::exists('guardians', 'email')]];
        }

        return [...$rules, ...SaveGuardianRequest::guardianRules()];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.exists' => __('No guardian of this school has this email address.')];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('email')) {
                    return;
                }

                $existing = $this->existingGuardian();

                if ($this->input('mode') === 'new' && $existing !== null) {
                    $validator->errors()->add('email', 'A guardian with this email already exists. Add them as an existing guardian instead.');
                }

                if ($this->input('mode') === 'existing' && $existing !== null
                    && $this->student()->guardians()->whereKey($existing->id)->exists()) {
                    $validator->errors()->add('email', __('This guardian is already linked to the student.'));
                }
            },
        ];
    }

    public function existingGuardian(): ?Guardian
    {
        return Guardian::query()->where('email', $this->string('email')->toString())->first();
    }

    /**
     * @return array{name: string, email?: string|null, phone?: string|null, relationship: string}
     */
    public function guardianData(): array
    {
        /** @var array{name: string, email?: string|null, phone?: string|null, relationship: string} */
        return $this->safe()->only(['name', 'email', 'phone', 'relationship']);
    }

    public function student(): Student
    {
        /** @var Student */
        return $this->route('student');
    }
}
