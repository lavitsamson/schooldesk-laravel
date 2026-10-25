<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Classes\DeleteClass;
use App\Actions\Classes\SaveClass;
use App\Actions\Classes\SyncTeachingAssignments;
use App\Http\Controllers\Controller;
use App\Http\Requests\Classes\SaveClassRequest;
use App\Http\Requests\Classes\SyncTeachingAssignmentsRequest;
use App\Http\Resources\SchoolClassResource;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SchoolClass::class);

        $years = AcademicYear::query()->orderByDesc('starts_on')->get();
        $year = $years->firstWhere('id', $request->integer('year_id')) ?? $years->firstWhere('is_current', true);

        $classes = $year === null ? collect() : SchoolClass::query()
            ->where('academic_year_id', $year->id)
            ->with('classTeacher')
            ->withCount(['enrollments', 'teachingAssignments'])
            ->orderBy('name')
            ->orderBy('section')
            ->get();

        return Inertia::render('Classes/Index', [
            'years' => $years->map(fn (AcademicYear $y): array => [
                'id' => $y->id, 'name' => $y->name, 'is_current' => $y->is_current,
            ])->values(),
            'yearId' => $year?->id,
            'classes' => SchoolClassResource::collection($classes)->resolve(),
            'teachers' => $this->teacherOptions(),
        ]);
    }

    public function store(SaveClassRequest $request, SaveClass $action): RedirectResponse
    {
        /** @var array{academic_year_id: int, name: string, section?: string|null, class_teacher_id?: int|null} $data */
        $data = $request->validated();
        $class = $action->handle(null, $data);

        return back()->with('status', __('Class :class added.', ['class' => $class->displayName()]));
    }

    public function show(SchoolClass $schoolClass): Response
    {
        Gate::authorize('view', $schoolClass);

        $schoolClass->load(['classTeacher', 'academicYear']);
        $assignments = TeachingAssignment::query()->where('class_id', $schoolClass->id)->pluck('teacher_id', 'subject_id');

        return Inertia::render('Classes/Show', [
            'schoolClass' => [
                ...SchoolClassResource::make($schoolClass)->resolve(),
                'academic_year' => ['id' => $schoolClass->academicYear->id, 'name' => $schoolClass->academicYear->name],
            ],
            'subjects' => Subject::query()->orderBy('name')->get()->map(fn (Subject $subject): array => [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'teacher_id' => $assignments->get($subject->id),
            ])->values(),
            'students' => Enrollment::query()
                ->where('class_id', $schoolClass->id)
                ->with('student')
                ->orderBy('roll_no')
                ->get()
                ->map(fn (Enrollment $e): array => [
                    'id' => $e->student->id,
                    'roll_no' => $e->roll_no,
                    'name' => $e->student->fullName(),
                    'admission_no' => $e->student->admission_no,
                    'status_label' => $e->student->status->label(),
                    'left_on' => $e->left_on?->toDateString(),
                ])->values(),
            'teachers' => $this->teacherOptions(),
        ]);
    }

    public function update(SaveClassRequest $request, SchoolClass $schoolClass, SaveClass $action): RedirectResponse
    {
        /** @var array{name: string, section?: string|null, class_teacher_id?: int|null} $data */
        $data = $request->validated();
        $action->handle($schoolClass, $data);

        return back()->with('status', __('Class saved.'));
    }

    public function updateAssignments(
        SyncTeachingAssignmentsRequest $request,
        SchoolClass $schoolClass,
        SyncTeachingAssignments $action,
    ): RedirectResponse {
        $action->handle($schoolClass, $request->assignments());

        return back()->with('status', __('Subject teachers saved.'));
    }

    public function destroy(SchoolClass $schoolClass, DeleteClass $action): RedirectResponse
    {
        Gate::authorize('delete', $schoolClass);

        $action->handle($schoolClass);

        return to_route('classes.index', ['year_id' => $schoolClass->academic_year_id])
            ->with('status', __('Class :class deleted.', ['class' => $schoolClass->displayName()]));
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function teacherOptions(): array
    {
        return User::query()->teachers()->orderBy('name')->get(['users.id', 'users.name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])
            ->values()
            ->all();
    }
}
