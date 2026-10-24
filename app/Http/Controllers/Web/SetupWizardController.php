<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Setup\ApplyGradeScalePreset;
use App\Actions\Setup\CompleteSetup;
use App\Actions\Setup\SaveAcademicYear;
use App\Actions\Setup\SyncClasses;
use App\Actions\Setup\SyncSubjects;
use App\Actions\Setup\UpdateSchoolDetails;
use App\Enums\GradeScalePreset;
use App\Enums\Locale;
use App\Enums\SetupStep;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\ApplyGradeScaleRequest;
use App\Http\Requests\Setup\SaveAcademicYearRequest;
use App\Http\Requests\Setup\SyncClassesRequest;
use App\Http\Requests\Setup\SyncSubjectsRequest;
use App\Http\Requests\Setup\UpdateSchoolDetailsRequest;
use App\Models\AcademicYear;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Support\CurrentSchool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SetupWizardController extends Controller
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function start(): RedirectResponse
    {
        return to_route('setup.show', SetupStep::SchoolDetails);
    }

    public function show(SetupStep $step): Response|RedirectResponse
    {
        $school = $this->currentSchool->get();
        Gate::authorize('update', $school);

        if ($school->isSetUp()) {
            return to_route('dashboard');
        }

        return Inertia::render($step->component(), [
            'steps' => array_map(
                fn (SetupStep $s): array => ['key' => $s->value, 'label' => $s->label()],
                SetupStep::cases(),
            ),
            'current' => $step->value,
            ...$this->propsFor($step, $school),
        ]);
    }

    public function updateSchool(UpdateSchoolDetailsRequest $request, UpdateSchoolDetails $action): RedirectResponse
    {
        /** @var array{name: string, country: string, timezone: string, locale: string, currency: string} $data */
        $data = $request->validated();
        $action->handle($this->currentSchool->get(), $data);

        return $this->next(SetupStep::SchoolDetails);
    }

    public function saveAcademicYear(SaveAcademicYearRequest $request, SaveAcademicYear $action): RedirectResponse
    {
        /** @var array{name: string, starts_on: string, ends_on: string, terms: list<array{name: string, starts_on: string, ends_on: string}>} $data */
        $data = $request->validated();
        $action->handle($data);

        return $this->next(SetupStep::AcademicYear);
    }

    public function syncClasses(SyncClassesRequest $request, SyncClasses $action): RedirectResponse
    {
        /** @var list<array{name: string, section?: string|null}> $classes */
        $classes = $request->validated('classes');
        $action->handle($classes);

        return $this->next(SetupStep::Classes);
    }

    public function syncSubjects(SyncSubjectsRequest $request, SyncSubjects $action): RedirectResponse
    {
        /** @var list<array{name: string, code: string}> $subjects */
        $subjects = $request->validated('subjects');
        $action->handle($subjects);

        return $this->next(SetupStep::Subjects);
    }

    public function applyGradeScale(
        ApplyGradeScaleRequest $request,
        ApplyGradeScalePreset $applyPreset,
        CompleteSetup $completeSetup,
    ): RedirectResponse {
        $applyPreset->handle($request->preset());
        $completeSetup->handle($this->currentSchool->get());

        return to_route('dashboard')->with('status', 'Your school is set up.');
    }

    private function next(SetupStep $step): RedirectResponse
    {
        return to_route('setup.show', $step->next() ?? $step);
    }

    /**
     * @return array<string, mixed>
     */
    private function propsFor(SetupStep $step, School $school): array
    {
        $year = AcademicYear::query()->where('is_current', true)->first();

        return match ($step) {
            SetupStep::SchoolDetails => [
                'school' => $school->only(['name', 'country', 'timezone', 'locale', 'currency']),
                'locales' => array_map(fn (Locale $l): array => ['value' => $l->value, 'label' => $l->label()], Locale::cases()),
                'currencies' => UpdateSchoolDetailsRequest::CURRENCIES,
                'timezones' => timezone_identifiers_list(),
            ],
            SetupStep::AcademicYear => [
                'academicYear' => $year === null ? null : [
                    'name' => $year->name,
                    'starts_on' => $year->starts_on->toDateString(),
                    'ends_on' => $year->ends_on->toDateString(),
                    'terms' => Term::query()
                        ->where('academic_year_id', $year->id)
                        ->orderBy('starts_on')
                        ->get()
                        ->map(fn (Term $term): array => [
                            'name' => $term->name,
                            'starts_on' => $term->starts_on->toDateString(),
                            'ends_on' => $term->ends_on->toDateString(),
                        ]),
                ],
            ],
            SetupStep::Classes => [
                'hasAcademicYear' => $year !== null,
                'classes' => $year === null ? [] : SchoolClass::query()
                    ->where('academic_year_id', $year->id)
                    ->orderBy('name')
                    ->orderBy('section')
                    ->get(['name', 'section']),
            ],
            SetupStep::Subjects => [
                'subjects' => Subject::query()->orderBy('name')->get(['name', 'code']),
            ],
            SetupStep::GradeScale => [
                'presets' => array_map(fn (GradeScalePreset $p): array => [
                    'value' => $p->value,
                    'label' => $p->label(),
                    'bands' => $p->bands(),
                ], GradeScalePreset::cases()),
            ],
        };
    }
}
