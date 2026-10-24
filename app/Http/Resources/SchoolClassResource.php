<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SchoolClass
 */
class SchoolClassResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'name' => $this->name,
            'section' => $this->section,
            'display_name' => $this->displayName(),
            'class_teacher' => $this->whenLoaded('classTeacher', fn (): ?array => $this->classTeacher === null ? null : [
                'id' => $this->classTeacher->id,
                'name' => $this->classTeacher->name,
            ]),
            'students_count' => $this->whenCounted('enrollments'),
            'subjects_count' => $this->whenCounted('teachingAssignments'),
        ];
    }
}
