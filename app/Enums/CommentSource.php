<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a report card's teacher comment came from. An AI draft only ever reaches a report
 * card after the teacher took it over into the editor and saved it, so there is no "ai" value.
 */
enum CommentSource: string
{
    case Manual = 'manual';
    case AiDraftEdited = 'ai_draft_edited';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Written by the teacher'),
            self::AiDraftEdited => __('AI draft, reviewed by the teacher'),
        };
    }
}
