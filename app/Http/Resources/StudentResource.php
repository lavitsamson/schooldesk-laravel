<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the student list. Deliberately without date of birth or address: the list does
 * not need them (data minimisation). StudentProfileResource has the full record.
 *
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $enrollment = $this->relationLoaded('currentEnrollment') ? $this->currentEnrollment : null;

        return [
            'id' => $this->id,
            'admission_no' => $this->admission_no,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->fullName(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'class' => $enrollment === null ? null : [
                'id' => $enrollment->class_id,
                'name' => $enrollment->schoolClass->displayName(),
                'roll_no' => $enrollment->roll_no,
            ],
            'photo_url' => $this->photo_path === null ? null : route('students.photo', [
                'student' => $this->id,
                // Changes with every upload, so browsers never show a cached old photo.
                'v' => hash('xxh3', $this->photo_path),
            ]),
        ];
    }
}
