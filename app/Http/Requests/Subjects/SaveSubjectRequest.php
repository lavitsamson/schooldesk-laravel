<?php

declare(strict_types=1);

namespace App\Http\Requests\Subjects;

use App\Models\Subject;
use App\Support\SchoolRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SaveSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->subject();

        return $this->user()?->can($subject === null ? 'create' : 'update', $subject ?? Subject::class) ?? false;
    }

    /**
     * Codes are stored in upper case, so they are compared in upper case too.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Str::upper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', SchoolRule::unique('subjects', 'code')->ignore($this->subject())],
        ];
    }

    public function subject(): ?Subject
    {
        /** @var Subject|null */
        return $this->route('subject');
    }
}
