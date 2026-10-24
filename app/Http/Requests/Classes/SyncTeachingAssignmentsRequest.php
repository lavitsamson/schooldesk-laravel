<?php

declare(strict_types=1);

namespace App\Http\Requests\Classes;

use App\Models\SchoolClass;
use App\Rules\SchoolTeacher;
use App\Support\SchoolRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncTeachingAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SchoolClass $class */
        $class = $this->route('schoolClass');

        return $this->user()?->can('update', $class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assignments' => ['present', 'array', 'max:50'],
            'assignments.*.subject_id' => ['required', 'integer', 'distinct', SchoolRule::exists('subjects')],
            'assignments.*.teacher_id' => ['nullable', 'integer', new SchoolTeacher],
        ];
    }

    /**
     * @return list<array{subject_id: int, teacher_id: int|null}>
     */
    public function assignments(): array
    {
        /** @var list<array{subject_id: int|string, teacher_id?: int|string|null}> $assignments */
        $assignments = $this->validated('assignments');

        return array_map(fn (array $a): array => [
            'subject_id' => (int) $a['subject_id'],
            'teacher_id' => isset($a['teacher_id']) ? (int) $a['teacher_id'] : null,
        ], $assignments);
    }
}
