<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of a student's enrolment history. Expects schoolClass and academicYear loaded.
 *
 * @mixin Enrollment
 */
class EnrollmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year' => $this->academicYear->name,
            'is_current_year' => $this->academicYear->is_current,
            'class' => ['id' => $this->class_id, 'name' => $this->schoolClass->displayName()],
            'roll_no' => $this->roll_no,
            'enrolled_on' => $this->enrolled_on->toDateString(),
            'left_on' => $this->left_on?->toDateString(),
        ];
    }
}
