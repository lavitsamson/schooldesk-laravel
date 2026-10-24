<?php

declare(strict_types=1);

namespace App\Http\Requests\Students;

use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of the student list. Unknown sort columns are rejected rather than passed on
 * to orderBy().
 */
class ListStudentsRequest extends FormRequest
{
    public const SORTS = ['name', 'admission_no', 'class'];

    public const ALL = 'all';

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Student::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'class_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([...array_column(StudentStatus::cases(), 'value'), self::ALL])],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * The filters as the list should show them, with defaults: active students, sorted by name.
     *
     * @return array{search: string, class_id: int|null, status: string, sort: string, direction: string}
     */
    public function filters(): array
    {
        return [
            'search' => trim($this->string('search')->toString()),
            'class_id' => $this->filled('class_id') ? $this->integer('class_id') : null,
            'status' => $this->string('status', StudentStatus::Active->value)->toString() ?: StudentStatus::Active->value,
            'sort' => $this->string('sort', 'name')->toString() ?: 'name',
            'direction' => $this->string('direction', 'asc')->toString() ?: 'asc',
        ];
    }
}
