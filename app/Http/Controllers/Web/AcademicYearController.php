<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\AcademicYears\CreateAcademicYear;
use App\Actions\AcademicYears\DeleteAcademicYear;
use App\Actions\AcademicYears\MakeAcademicYearCurrent;
use App\Actions\AcademicYears\UpdateAcademicYear;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicYears\StoreAcademicYearRequest;
use App\Http\Requests\AcademicYears\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AcademicYear::class);

        $years = AcademicYear::query()
            ->with(['terms' => fn ($q) => $q->orderBy('starts_on')])
            ->withCount(['classes', 'enrollments'])
            ->orderByDesc('starts_on')
            ->get();

        $current = $years->firstWhere('is_current', true);

        return Inertia::render('AcademicYears/Index', [
            'years' => AcademicYearResource::collection($years)->resolve(),
            'canPromote' => $current !== null
                && $years->contains(fn (AcademicYear $y): bool => $y->starts_on->gt($current->starts_on))
                && ($request->user()?->can('promote', Student::class) ?? false),
        ]);
    }

    public function store(StoreAcademicYearRequest $request, CreateAcademicYear $action): RedirectResponse
    {
        /** @var array{name: string, starts_on: string, ends_on: string, terms: list<array{name: string, starts_on: string, ends_on: string}>} $data */
        $data = $request->safe()->except('copy_classes_from');
        $year = $action->handle($data, $request->copyClassesFrom());

        return back()->with('status', __('Academic year :name added.', ['name' => $year->name]));
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear, UpdateAcademicYear $action): RedirectResponse
    {
        /** @var array{name: string, starts_on: string, ends_on: string, terms: list<array{id?: int|null, name: string, starts_on: string, ends_on: string}>} $data */
        $data = $request->validated();
        $action->handle($academicYear, $data);

        return back()->with('status', __('Academic year :name saved.', ['name' => $academicYear->name]));
    }

    public function makeCurrent(AcademicYear $academicYear, MakeAcademicYearCurrent $action): RedirectResponse
    {
        Gate::authorize('update', $academicYear);

        $action->handle($academicYear);

        return back()->with('status', __(':name is now the current academic year.', ['name' => $academicYear->name]));
    }

    public function destroy(AcademicYear $academicYear, DeleteAcademicYear $action): RedirectResponse
    {
        Gate::authorize('delete', $academicYear);

        $action->handle($academicYear);

        return back()->with('status', __('Academic year :name deleted.', ['name' => $academicYear->name]));
    }
}
