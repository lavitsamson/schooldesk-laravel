<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Fees\SaveFeeStructure;
use App\Enums\FeeFrequency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\SaveFeeStructureRequest;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Support\CurrentSchool;
use App\Support\EnumOptions;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The fees of the current academic year, per class or for every class.
 */
class FeeStructureController extends Controller
{
    public function index(CurrentSchool $currentSchool): Response
    {
        Gate::authorize('viewAny', FeeStructure::class);

        $year = AcademicYear::query()->current()->firstOrFail();
        $currency = $currentSchool->get()->currency;

        return Inertia::render('Fees/Structures', [
            'year' => ['id' => $year->id, 'name' => $year->name],
            'feeStructures' => FeeStructure::query()
                ->where('academic_year_id', $year->id)
                ->with('schoolClass')
                ->orderByRaw('class_id is not null')
                ->orderBy('name')
                ->get()
                ->map(fn (FeeStructure $fee): array => [
                    'id' => $fee->id,
                    'name' => $fee->name,
                    'class_id' => $fee->class_id,
                    'class' => $fee->schoolClass?->displayName(),
                    'amount' => $fee->amount,
                    // Prefills the edit form: "1200.00".
                    'amount_input' => (new Money($fee->amount, $currency))->toDecimal(),
                    'frequency' => $fee->frequency->value,
                    'frequency_label' => $fee->frequency->label(),
                    'due_day_offset' => $fee->due_day_offset,
                ])->values(),
            'classes' => $year->classes()->orderBy('name')->orderBy('section')->get()
                ->map(fn (SchoolClass $class): array => ['id' => $class->id, 'name' => $class->displayName()])
                ->values(),
            'frequencies' => EnumOptions::of(FeeFrequency::class),
        ]);
    }

    public function store(SaveFeeStructureRequest $request, SaveFeeStructure $action): RedirectResponse
    {
        $fee = $action->handle(null, $request->feeData());

        return back()->with('status', __('Fee :name added.', ['name' => $fee->name]));
    }

    public function update(SaveFeeStructureRequest $request, FeeStructure $feeStructure, SaveFeeStructure $action): RedirectResponse
    {
        $action->handle($feeStructure, $request->feeData());

        return back()->with('status', __('Fee saved. It applies to invoices generated from now on.'));
    }

    public function destroy(FeeStructure $feeStructure): RedirectResponse
    {
        Gate::authorize('delete', $feeStructure);

        $feeStructure->delete();

        return back()->with('status', __('Fee :name deleted. Issued invoices keep it.', ['name' => $feeStructure->name]));
    }
}
