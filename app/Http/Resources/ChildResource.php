<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of a parent's children (parent portal and API). Expects currentEnrollment.schoolClass
 * (ListChildren). Personal details beyond the name stay in the school office.
 *
 * @mixin Student
 */
class ChildResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $class = $this->currentEnrollment?->schoolClass;

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->fullName(),
            'admission_no' => $this->admission_no,
            'status' => $this->status->value,
            'class' => $class === null ? null : ['id' => $class->id, 'name' => $class->displayName()],
        ];
    }
}
