<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Guardian
 */
class GuardianResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'relationship' => $this->relationship->value,
            'relationship_label' => $this->relationship->label(),
            'has_portal_access' => $this->user_id !== null,
            // Only when loaded through a student's guardians.
            'is_primary' => $this->whenPivotLoaded('guardian_student', fn (): bool => (bool) $this->pivot?->is_primary),
            'students' => $this->whenLoaded('students', fn () => $this->students->map(fn (Student $s): array => [
                'id' => $s->id,
                'name' => $s->fullName(),
            ])->values()),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'invite' => $request->user()?->can('invite', $this->resource) ?? false,
                'delete' => $this->relationLoaded('students') && ($request->user()?->can('delete', $this->resource) ?? false),
            ],
        ];
    }
}
