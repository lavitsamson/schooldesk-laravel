<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Enums\CommentSource;
use App\Models\AiCommentDraft;
use App\Models\ReportCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the teacher's comment on a draft report card. When the teacher started from an AI
 * draft (and edited it, or not), the draft is marked as accepted and the comment's source
 * says so; later edits keep that source, since the text still started from the draft.
 */
final class SaveReportCardComment
{
    public function handle(ReportCard $card, ?string $comment, ?AiCommentDraft $draft = null): ReportCard
    {
        if (! $card->isDraft()) {
            throw ValidationException::withMessages(['comment' => 'Reopen the report card to change the comment.']);
        }

        $comment = $comment === null ? null : (trim($comment) === '' ? null : trim($comment));
        $fromDraft = $comment !== null && ($draft !== null || $card->comment_source === CommentSource::AiDraftEdited);

        return DB::transaction(function () use ($card, $comment, $draft, $fromDraft): ReportCard {
            $draft?->update(['accepted' => true]);

            $card->update([
                'teacher_comment' => $comment,
                'comment_source' => match (true) {
                    $comment === null => null,
                    $fromDraft => CommentSource::AiDraftEdited,
                    default => CommentSource::Manual,
                },
            ]);

            return $card;
        });
    }
}
