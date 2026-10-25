<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A comment the LLM suggested for a report card. It is only a suggestion: the teacher may
 * take it over into the comment editor, edit it and save it (accepted), or ignore it. The
 * data sent is not stored, only its hash and the token usage.
 *
 * @property int $id
 * @property int $report_card_id
 * @property int|null $user_id
 * @property string $prompt_hash
 * @property string $draft_text
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property bool $accepted
 * @property Carbon $created_at
 */
#[Fillable(['school_id', 'report_card_id', 'user_id', 'prompt_hash', 'draft_text', 'model', 'input_tokens', 'output_tokens', 'accepted'])]
class AiCommentDraft extends Model
{
    use BelongsToSchool;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ReportCard, $this>
     */
    public function reportCard(): BelongsTo
    {
        return $this->belongsTo(ReportCard::class);
    }
}
