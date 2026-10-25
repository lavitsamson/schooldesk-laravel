<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Promotions\PreviewPromotion;
use App\Actions\Promotions\PromoteStudents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotions\PromoteStudentsRequest;
use App\Models\AcademicYear;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * End of year: promote the current year's students into next year's classes, or graduate them.
 * The page is the preview; nothing changes until the admin confirms.
 */
class PromotionController extends Controller
{
    public function create(Request $request, PreviewPromotion $preview): Response
    {
        Gate::authorize('promote', Student::class);

        $current = AcademicYear::query()->current()->firstOrFail();
        $later = AcademicYear::query()->where('starts_on', '>', $current->starts_on)->orderBy('starts_on')->get();
        $target = $later->firstWhere('id', $request->integer('to_year_id')) ?? $later->first();

        return Inertia::render('Promotions/Create', [
            'fromYear' => ['id' => $current->id, 'name' => $current->name],
            'years' => $later->map(fn (AcademicYear $y): array => ['id' => $y->id, 'name' => $y->name])->values(),
            'toYearId' => $target?->id,
            'preview' => $target === null ? null : $preview->handle($current, $target),
        ]);
    }

    public function store(PromoteStudentsRequest $request, PromoteStudents $action): RedirectResponse
    {
        /** @var AcademicYear $from validated in the request */
        $from = $request->fromYear();
        $to = $request->toYear();

        $result = $action->handle($from, $to, $request->mappings(), $request->excludedStudentIds());

        return to_route('academic-years.index')->with('status', __(
            ':promoted promoted to :year, :graduated graduated, :skipped not promoted. Make :year the current year when it starts.',
            [
                'promoted' => $result['promoted'],
                'year' => $to->name,
                'graduated' => $result['graduated'],
                'skipped' => $result['skipped'],
            ],
        ));
    }
}
