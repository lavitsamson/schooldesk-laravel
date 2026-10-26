<?php

declare(strict_types=1);

namespace App\Http\Requests\Gdpr;

use App\Support\CurrentSchool;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /data-protection: the school's retention setting for attendance notes (null: kept).
 */
class UpdateDataProtectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageDataProtection', app(CurrentSchool::class)->get()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attendance_note_retention_months' => ['present', 'nullable', 'integer', Rule::in(config('schooldesk.gdpr.attendance_note_retention_months'))],
        ];
    }

    public function retentionMonths(): ?int
    {
        return $this->filled('attendance_note_retention_months') ? $this->integer('attendance_note_retention_months') : null;
    }
}
