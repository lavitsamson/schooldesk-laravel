<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Models\School;

/**
 * Turns AI comment drafts on or off for the school. Off by default: a school admin decides
 * whether report data (minimised, see BuildCommentDraftRequest) may go to the LLM provider.
 */
final class UpdateAiCommentDrafts
{
    public function handle(School $school, bool $enabled): void
    {
        $school->update(['settings' => [...($school->settings ?? []), School::SETTING_AI_COMMENT_DRAFTS => $enabled]]);
    }
}
