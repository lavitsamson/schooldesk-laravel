<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Grades\BuildGradebook;
use App\Actions\Grades\ListGradebooks;
use App\Enums\AssessmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Grades\GradebookRequest;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Grade;
use App\Models\GradeScale;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\TermChoice;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GradeController extends Controller
{
    /**
     * The class-subjects the user enters marks for in a term of the current year.
     */
    public function index(Request $request, #[CurrentUser] User $user, ListGradebooks $action): Response
    {
        Gate::authorize('viewAny', Assessment::class);

        $year = AcademicYear::query()->current()->with('terms')->firstOrFail();
        $term = TermChoice::pick($year, $request->query('term'));

        return Inertia::render('Grades/Index', [
            'year' => $year->name,
            'terms' => TermChoice::options($year),
            'term' => $this->term($term),
            'classes' => $action->handle($user, $term),
            'allClasses' => $user->can('viewAllClasses', Assessment::class),
            'canEditScale' => $user->can('update', GradeScale::class),
        ]);
    }

    public function gradebook(GradebookRequest $request, SchoolClass $schoolClass, Subject $subject, #[CurrentUser] User $user, BuildGradebook $action): Response
    {
        $term = $request->term();

        return Inertia::render('Grades/Gradebook', [
            'schoolClass' => ['id' => $schoolClass->id, 'name' => $schoolClass->displayName()],
            'subject' => ['id' => $subject->id, 'name' => $subject->name],
            'terms' => TermChoice::options($schoolClass->academicYear),
            'term' => $this->term($term),
            ...$action->handle($schoolClass, $subject, $term),
            'types' => array_map(fn (AssessmentType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'weight' => $type->defaultWeight(),
            ], AssessmentType::cases()),
            'canViewResults' => $user->can('viewResults', [Grade::class, $schoolClass]),
        ]);
    }

    /**
     * @return array{id: int, name: string, starts_on: string, ends_on: string}
     */
    private function term(Term $term): array
    {
        return [
            'id' => $term->id,
            'name' => $term->name,
            'starts_on' => $term->starts_on->toDateString(),
            'ends_on' => $term->ends_on->toDateString(),
        ];
    }
}
