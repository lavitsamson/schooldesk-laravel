<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\ReportCards\UpdateAiCommentDrafts;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReportCards\UpdateAiCommentDraftsRequest;
use App\Support\CurrentSchool;
use Illuminate\Http\RedirectResponse;

class ReportCardSettingsController extends Controller
{
    public function updateAiDrafts(UpdateAiCommentDraftsRequest $request, CurrentSchool $currentSchool, UpdateAiCommentDrafts $action): RedirectResponse
    {
        $enabled = $request->boolean('enabled');
        $action->handle($currentSchool->get(), $enabled);

        return back()->with('status', $enabled ? 'AI comment drafts turned on.' : 'AI comment drafts turned off.');
    }
}
