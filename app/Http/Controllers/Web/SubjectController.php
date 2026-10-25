<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Subjects\DeleteSubject;
use App\Actions\Subjects\SaveSubject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subjects\SaveSubjectRequest;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SubjectController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Subject::class);

        return Inertia::render('Subjects/Index', [
            'subjects' => Subject::query()
                ->withCount('teachingAssignments')
                ->orderBy('name')
                ->get()
                ->map(fn (Subject $subject): array => [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'classes_count' => $subject->teaching_assignments_count ?? 0,
                ])->values(),
        ]);
    }

    public function store(SaveSubjectRequest $request, SaveSubject $action): RedirectResponse
    {
        /** @var array{name: string, code: string} $data */
        $data = $request->validated();
        $subject = $action->handle(null, $data);

        return back()->with('status', __('Subject :name added.', ['name' => $subject->name]));
    }

    public function update(SaveSubjectRequest $request, Subject $subject, SaveSubject $action): RedirectResponse
    {
        /** @var array{name: string, code: string} $data */
        $data = $request->validated();
        $action->handle($subject, $data);

        return back()->with('status', __('Subject saved.'));
    }

    public function destroy(Subject $subject, DeleteSubject $action): RedirectResponse
    {
        Gate::authorize('delete', $subject);

        $action->handle($subject);

        return back()->with('status', __('Subject :name deleted.', ['name' => $subject->name]));
    }
}
