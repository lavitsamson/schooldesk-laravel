<?php

declare(strict_types=1);

namespace App\Http\Requests\ReportCards;

use App\Models\AiCommentDraft;
use App\Models\ReportCard;
use App\Support\SchoolRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /report-cards/{reportCard}/comment: the comment, and the AI draft it started from (if
 * any), which must be a draft of this report card.
 */
class SaveReportCardCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->reportCard()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comment' => ['nullable', 'string', 'max:'.(int) config('schooldesk.report_cards.comment_max_length')],
            'ai_draft_id' => [
                'nullable',
                'integer',
                SchoolRule::exists('ai_comment_drafts')->where('report_card_id', $this->reportCard()->id),
            ],
        ];
    }

    public function reportCard(): ReportCard
    {
        /** @var ReportCard */
        return $this->route('reportCard');
    }

    public function comment(): ?string
    {
        $comment = $this->validated('comment');

        return is_string($comment) ? $comment : null;
    }

    public function draft(): ?AiCommentDraft
    {
        $id = $this->validated('ai_draft_id');

        return $id === null ? null : AiCommentDraft::query()->findOrFail((int) $id);
    }
}
