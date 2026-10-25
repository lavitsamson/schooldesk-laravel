<?php

declare(strict_types=1);

namespace App\Actions\ReportCards;

use App\Models\AiCommentDraft;
use App\Models\ReportCard;
use App\Models\User;
use App\Services\Llm\LlmClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Asks the LLM for a comment draft and stores it beside the report card, never in it: the
 * teacher decides whether to take it over (SaveReportCardComment), edits it, and approves the
 * card. Token usage is stored per draft and logged. Rate limit and the school's on/off
 * setting are checked before this (route throttle, ReportCardPolicy::draftComment).
 */
final class DraftReportCardComment
{
    public function __construct(
        private readonly BuildCommentDraftRequest $buildRequest,
        private readonly LlmClient $llm,
    ) {}

    public function handle(ReportCard $card, User $user): AiCommentDraft
    {
        if (! $card->isDraft()) {
            throw ValidationException::withMessages(['draft' => 'Reopen the report card to change the comment.']);
        }

        $request = $this->buildRequest->handle($card);
        $response = $this->llm->complete($request);

        $draft = AiCommentDraft::query()->create([
            'report_card_id' => $card->id,
            'user_id' => $user->id,
            'prompt_hash' => $request->hash($response->model),
            'draft_text' => $response->text,
            'model' => $response->model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
        ]);

        Log::info('AI comment draft created', [
            'school_id' => $card->school_id,
            'report_card_id' => $card->id,
            'draft_id' => $draft->id,
            'model' => $response->model,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
        ]);

        return $draft;
    }
}
