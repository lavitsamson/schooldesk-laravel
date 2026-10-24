<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Grades\LoadGradeSheet;
use App\Actions\Grades\RecordGrades;
use App\Actions\Grades\SaveAssessment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Grades\SaveAssessmentRequest;
use App\Http\Requests\Grades\SaveGradesRequest;
use App\Models\Assessment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    /**
     * Adds an assessment to the class-subject and goes straight to entering its marks.
     */
    public function store(SaveAssessmentRequest $request, SchoolClass $schoolClass, Subject $subject, #[CurrentUser] User $user, SaveAssessment $action): RedirectResponse
    {
        $assessment = $action->handle(new Assessment([
            'class_id' => $schoolClass->id,
            'subject_id' => $subject->id,
            'term_id' => $request->term()->id,
            'created_by' => $user->id,
        ]), $request->assessmentData());

        return to_route('assessments.grades', $assessment)->with('status', "{$assessment->name} added. Enter the marks below.");
    }

    public function update(SaveAssessmentRequest $request, Assessment $assessment, SaveAssessment $action): RedirectResponse
    {
        $action->handle($assessment, $request->assessmentData());

        return back()->with('status', "{$assessment->name} saved.");
    }

    /**
     * Its marks are removed with it.
     */
    public function destroy(Assessment $assessment): RedirectResponse
    {
        Gate::authorize('delete', $assessment);

        $assessment->delete();

        return to_route('grades.gradebook', [$assessment->class_id, $assessment->subject_id, 'term' => $assessment->term_id])
            ->with('status', "{$assessment->name} deleted.");
    }

    /**
     * The mark entry grid.
     */
    public function grades(Assessment $assessment, LoadGradeSheet $action): Response
    {
        Gate::authorize('enterGrades', $assessment);

        return Inertia::render('Grades/Sheet', [
            'assessment' => [
                'id' => $assessment->id,
                'name' => $assessment->name,
                'type' => $assessment->type->value,
                'type_label' => $assessment->type->label(),
                'held_on' => $assessment->held_on->toDateString(),
                'max_marks' => $assessment->max_marks,
                'weight' => $assessment->weight,
            ],
            'schoolClass' => ['id' => $assessment->schoolClass->id, 'name' => $assessment->schoolClass->displayName()],
            'subject' => ['id' => $assessment->subject->id, 'name' => $assessment->subject->name],
            'term' => ['id' => $assessment->term->id, 'name' => $assessment->term->name],
            ...$action->handle($assessment),
        ]);
    }

    /**
     * Autosave from the grid: JSON in, JSON out (validation errors are a 422 with the field keys).
     */
    public function saveGrades(SaveGradesRequest $request, Assessment $assessment, #[CurrentUser] User $user, RecordGrades $action): JsonResponse
    {
        $result = $action->handle($assessment, $request->entries(), $user);

        return response()->json([...$result, 'saved_at' => now()->toIso8601String()]);
    }
}
