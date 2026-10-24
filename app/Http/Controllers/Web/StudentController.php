<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Students\ListStudents;
use App\Actions\Students\SaveStudent;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\StudentBulkAction;
use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\ListStudentsRequest;
use App\Http\Requests\Students\SaveStudentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\GuardianResource;
use App\Http\Resources\StudentProfileResource;
use App\Http\Resources\StudentResource;
use App\Models\AcademicYear;
use App\Models\ImportBatch;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\EnumOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(ListStudentsRequest $request, ListStudents $action): Response
    {
        $filters = $request->filters();

        return Inertia::render('Students/Index', [
            'students' => StudentResource::collection($action->handle($filters)),
            'filters' => $filters,
            'classes' => $this->currentClasses(),
            'statuses' => EnumOptions::of(StudentStatus::class),
            'bulkActions' => EnumOptions::of(StudentBulkAction::class),
            'can' => [
                'create' => $request->user()?->can('create', Student::class) ?? false,
                'import' => $request->user()?->can('create', ImportBatch::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Student::class);

        return Inertia::render('Students/Create', [
            'classes' => $this->currentClasses(),
            'genders' => EnumOptions::of(Gender::class),
        ]);
    }

    public function store(SaveStudentRequest $request, SaveStudent $action): RedirectResponse
    {
        $student = $action->handle(null, $request->studentData(), $request->photo());

        return to_route('students.show', $student)->with('status', "{$student->fullName()} added.");
    }

    public function show(Student $student): Response
    {
        Gate::authorize('view', $student);

        $student->load([
            'currentEnrollment.schoolClass',
            'guardians' => fn ($q) => $q->orderByDesc('guardian_student.is_primary')->orderBy('name'),
            'enrollments' => fn ($q) => $q->with(['schoolClass', 'academicYear'])->orderByDesc('enrolled_on'),
        ]);

        return Inertia::render('Students/Show', [
            'student' => StudentProfileResource::make($student)->resolve(),
            'guardians' => GuardianResource::collection($student->guardians)->resolve(),
            'enrollments' => EnrollmentResource::collection($student->enrollments)->resolve(),
            'classes' => $this->currentClasses(),
            'relationships' => EnumOptions::of(GuardianRelationship::class),
        ]);
    }

    public function edit(Student $student): Response
    {
        Gate::authorize('update', $student);

        $student->load('currentEnrollment.schoolClass');

        return Inertia::render('Students/Edit', [
            'student' => StudentProfileResource::make($student)->resolve(),
            'genders' => EnumOptions::of(Gender::class),
            'statuses' => EnumOptions::of(StudentStatus::class),
        ]);
    }

    public function update(SaveStudentRequest $request, Student $student, SaveStudent $action): RedirectResponse
    {
        $action->handle($student, $request->studentData(), $request->photo(), $request->boolean('remove_photo'));

        return to_route('students.show', $student)->with('status', 'Student saved.');
    }

    /**
     * Soft delete: for records created by mistake. Students who leave are marked as left.
     */
    public function destroy(Student $student): RedirectResponse
    {
        Gate::authorize('delete', $student);

        $student->delete();

        return to_route('students.index')->with('status', "{$student->fullName()} deleted.");
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function currentClasses(): array
    {
        return SchoolClass::query()
            ->whereIn('academic_year_id', AcademicYear::query()->current()->select('id'))
            ->orderBy('name')
            ->orderBy('section')
            ->get()
            ->map(fn (SchoolClass $class): array => ['id' => $class->id, 'name' => $class->displayName()])
            ->values()
            ->all();
    }
}
