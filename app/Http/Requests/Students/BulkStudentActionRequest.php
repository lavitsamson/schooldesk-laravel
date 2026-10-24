<?php

declare(strict_types=1);

namespace App\Http\Requests\Students;

use App\Enums\StudentBulkAction;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\SchoolRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkStudentActionRequest extends FormRequest
{
    public const MAX_STUDENTS = 500;

    /** @var Collection<int, Student>|null */
    private ?Collection $students = null;

    public function authorize(): bool
    {
        return $this->user()?->can('updateMany', Student::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(StudentBulkAction::class)],
            'student_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_STUDENTS],
            'student_ids.*' => ['integer', 'distinct'],
            'class_id' => [
                Rule::requiredIf($this->input('action') === StudentBulkAction::MoveToClass->value),
                'nullable',
                'integer',
                SchoolRule::exists('classes')->where('academic_year_id', AcademicYear::query()->current()->value('id')),
            ],
        ];
    }

    /**
     * One query for all ids instead of an exists rule per id. Ids of another school (or of
     * deleted students) are not found by the scoped query, so the request fails as a whole.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var list<int> $ids */
                $ids = array_map('intval', (array) $this->input('student_ids'));

                if ($this->students()->count() !== count($ids)) {
                    $validator->errors()->add('student_ids', 'Some of the selected students no longer exist. Reload the list and try again.');
                }
            },
        ];
    }

    public function action(): StudentBulkAction
    {
        return StudentBulkAction::from($this->string('action')->toString());
    }

    /**
     * @return Collection<int, Student>
     */
    public function students(): Collection
    {
        return $this->students ??= Student::query()
            ->whereKey(array_map('intval', (array) $this->input('student_ids')))
            ->get();
    }

    public function targetClass(): SchoolClass
    {
        return SchoolClass::query()->findOrFail($this->integer('class_id'));
    }
}
