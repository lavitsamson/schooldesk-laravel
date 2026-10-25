<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\ReportCards\DraftReportCardComment;
use App\Actions\ReportCards\SaveReportCardComment;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportCards\SaveReportCardCommentRequest;
use App\Models\ReportCard;
use App\Models\User;
use App\Services\Llm\LlmException;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The teacher's comment on a report card, and AI drafts for it (ADR 0007).
 */
class ReportCardCommentController extends Controller
{
    public function update(SaveReportCardCommentRequest $request, ReportCard $reportCard, SaveReportCardComment $action): RedirectResponse
    {
        $action->handle($reportCard, $request->comment(), $request->draft());

        return back()->with('status', 'Comment saved.');
    }

    /**
     * Rate limited per school (throttle:ai-drafts). The draft is shown beside the editor; it
     * is not written into the comment.
     */
    public function draft(ReportCard $reportCard, #[CurrentUser] User $user, DraftReportCardComment $action): RedirectResponse
    {
        Gate::authorize('draftComment', $reportCard);

        try {
            $action->handle($reportCard, $user);
        } catch (LlmException $e) {
            report($e);

            return back()->withErrors(['draft' => 'No draft could be written just now. Please try again, or write the comment yourself.']);
        }

        return back()->with('status', 'Draft ready: review it before using it.');
    }
}
