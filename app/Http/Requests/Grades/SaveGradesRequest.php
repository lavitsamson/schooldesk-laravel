<?php

declare(strict_types=1);

namespace App\Http\Requests\Grades;

use App\Actions\Grades\LoadGradeSheet;
use App\Models\Assessment;
use App\Support\Grading\Decimal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PUT /assessments/{assessment}/grades (JSON, from the grid's autosave): the rows that changed.
 */
class SaveGradesRequest extends FormRequest
{
    /**
     * More than any class has; a guard against oversized requests.
     */
    public const MAX_STUDENTS = 200;

    public function authorize(): bool
    {
        return $this->user()?->can('enterGrades', $this->assessment()) ?? false;
    }

    /**
     * Blank cells become null, and 12,5 (as typed on a German keyboard) becomes 12.5.
     */
    protected function prepareForValidation(): void
    {
        $grades = $this->input('grades');

        if (! is_array($grades)) {
            return;
        }

        $this->merge(['grades' => array_map(function (mixed $row): mixed {
            if (! is_array($row)) {
                return $row;
            }

            foreach (['marks', 'remarks'] as $field) {
                if (is_string($row[$field] ?? null)) {
                    $value = trim($row[$field]);
                    $row[$field] = $value === '' ? null : ($field === 'marks' ? str_replace(',', '.', $value) : $value);
                }
            }

            return $row;
        }, $grades)]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1', 'max:'.self::MAX_STUDENTS],
            'grades.*.student_id' => ['required', 'integer', 'distinct'],
            'grades.*.marks' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.$this->assessment()->max_marks],
            'grades.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grades.*.marks.max' => 'At most :max marks.',
            'grades.*.marks.min' => 'Marks cannot be negative.',
            'grades.*.marks.numeric' => 'Enter a number.',
            'grades.*.marks.decimal' => 'At most two decimals.',
        ];
    }

    /**
     * Every student must be on the sheet: in the class on the day, or already marked. One
     * query for all of them, and ids of other schools are never on it.
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

                $unknown = array_diff(array_column($this->entries(), 'student_id'), LoadGradeSheet::studentIds($this->assessment()));

                if ($unknown !== []) {
                    $validator->errors()->add('grades', 'Some students are not in this class on the day of the assessment. Reload the page and try again.');
                }
            },
        ];
    }

    public function assessment(): Assessment
    {
        /** @var Assessment */
        return $this->route('assessment');
    }

    /**
     * @return list<array{student_id: int, marks: string|null, remarks: string|null}>
     */
    public function entries(): array
    {
        /** @var array<int, array{student_id: int|string, marks?: string|int|float|null, remarks?: string|null}> $grades */
        $grades = (array) $this->input('grades', []);

        return array_values(array_map(fn (array $row): array => [
            'student_id' => (int) $row['student_id'],
            'marks' => isset($row['marks']) ? Decimal::fixed($row['marks']) : null,
            'remarks' => $row['remarks'] ?? null,
        ], $grades));
    }
}
