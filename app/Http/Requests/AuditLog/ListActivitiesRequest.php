<?php

declare(strict_types=1);

namespace App\Http\Requests\AuditLog;

use App\Enums\ActivityArea;
use App\Models\Activity;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of the audit log: area, who made the change, a date range (in the school's
 * time zone) and one student's history.
 *
 * @phpstan-type ActivityFilters array{area: string|null, user_id: int|null, student_id: int|null, from: string|null, to: string|null}
 */
class ListActivitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Activity::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'area' => ['nullable', Rule::enum(ActivityArea::class)],
            'user_id' => ['nullable', 'integer'],
            'student' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * The student whose history is shown: another school's student is not found (404).
     */
    public function student(): ?Student
    {
        return $this->filled('student')
            ? Student::query()->withTrashed()->findOrFail($this->integer('student'))
            : null;
    }

    /**
     * @return ActivityFilters
     */
    public function filters(?Student $student): array
    {
        return [
            'area' => $this->string('area')->toString() ?: null,
            'user_id' => $this->filled('user_id') ? $this->integer('user_id') : null,
            'student_id' => $student?->id,
            'from' => $this->string('from')->toString() ?: null,
            'to' => $this->string('to')->toString() ?: null,
        ];
    }
}
