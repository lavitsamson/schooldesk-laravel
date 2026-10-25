<?php

declare(strict_types=1);

namespace App\Http\Requests\Students;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Support\SchoolRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Creating (POST) or updating (PUT /students/{student}) a student. The class is chosen on
 * create only; later class changes use "Move to class". The status is changed on update only.
 */
class SaveStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->student();

        return $this->user()?->can($student === null ? 'create' : 'update', $student ?? Student::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->student() === null;

        return [
            'admission_no' => [
                'required', 'string', 'max:30', 'regex:'.Student::ADMISSION_NO_PATTERN,
                SchoolRule::unique('students', 'admission_no')->ignore($this->student()),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'address' => ['nullable', 'string', 'max:500'],
            'class_id' => $creating
                ? ['nullable', 'integer', SchoolRule::exists('classes')->where('academic_year_id', $this->currentYearId())]
                : ['prohibited'],
            'status' => $creating ? ['prohibited'] : ['required', Rule::enum(StudentStatus::class)],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_photo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'admission_no.unique' => __('This admission number is already used (admission numbers are never reused).'),
            'admission_no.regex' => __('Use letters, digits and - _ . / only.'),
            'class_id.exists' => __('Choose a class of the current academic year.'),
        ];
    }

    /**
     * @return array{admission_no: string, first_name: string, last_name: string, date_of_birth: string, gender?: string|null, address?: string|null, status?: string, class_id?: int|null}
     */
    public function studentData(): array
    {
        /** @var array{admission_no: string, first_name: string, last_name: string, date_of_birth: string, gender?: string|null, address?: string|null, status?: string, class_id?: int|null} */
        return $this->safe()->except(['photo', 'remove_photo']);
    }

    public function photo(): ?UploadedFile
    {
        $photo = $this->file('photo');

        return $photo instanceof UploadedFile ? $photo : null;
    }

    public function student(): ?Student
    {
        /** @var Student|null */
        return $this->route('student');
    }

    private function currentYearId(): ?int
    {
        $id = AcademicYear::query()->current()->value('id');

        return is_int($id) ? $id : null;
    }
}
