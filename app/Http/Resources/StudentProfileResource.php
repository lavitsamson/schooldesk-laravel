<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Student;
use App\Support\LocalDate;
use Illuminate\Http\Request;

/**
 * The full student record, for the profile and edit pages.
 *
 * @mixin Student
 */
class StudentProfileResource extends StudentResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            // Only a placeholder once anonymised.
            'date_of_birth' => $this->anonymised_at === null ? $this->date_of_birth->toDateString() : null,
            'gender' => $this->gender?->value,
            'gender_label' => $this->gender?->label(),
            'address' => $this->address,
            'anonymised_on' => $this->anonymised_at === null ? null : LocalDate::short($this->anonymised_at),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                'export' => $request->user()?->can('exportData', $this->resource) ?? false,
                'anonymise' => $request->user()?->can('anonymise', $this->resource) ?? false,
            ],
        ];
    }
}
