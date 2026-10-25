<?php

declare(strict_types=1);

namespace App\Http\Requests\ReportCards;

use App\Support\CurrentSchool;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /report-cards/settings/ai-drafts: school admins turn AI comment drafts on or off.
 */
class UpdateAiCommentDraftsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', app(CurrentSchool::class)->get()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }
}
