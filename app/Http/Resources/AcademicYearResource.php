<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AcademicYear
 */
class AcademicYearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'is_current' => $this->is_current,
            'terms' => $this->whenLoaded('terms', fn () => $this->terms->map(fn (Term $term): array => [
                'id' => $term->id,
                'name' => $term->name,
                'starts_on' => $term->starts_on->toDateString(),
                'ends_on' => $term->ends_on->toDateString(),
            ])->values()),
            'classes_count' => $this->whenCounted('classes'),
            'enrollments_count' => $this->whenCounted('enrollments'),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
