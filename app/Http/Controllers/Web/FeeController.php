<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Fees\BuildFeesDashboard;
use App\Actions\Fees\GenerateTermInvoices;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\GenerateInvoicesRequest;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Services\StripeService;
use App\Support\TermChoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The fees dashboard: collected against outstanding per class for a term, and generating the
 * term's invoices now instead of waiting for the daily run (ADR 0008).
 */
class FeeController extends Controller
{
    public function index(Request $request, BuildFeesDashboard $action, StripeService $stripe): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $year = AcademicYear::query()->current()->with('terms')->firstOrFail();
        $term = TermChoice::pick($year, $request->query('term'));

        return Inertia::render('Fees/Index', [
            'year' => $year->name,
            'terms' => TermChoice::options($year),
            'term' => ['id' => $term->id, 'name' => $term->name, 'starts_on' => $term->starts_on->toDateString()],
            'dashboard' => $action->handle($term),
            'feeStructures' => FeeStructure::query()->where('academic_year_id', $year->id)->count(),
            'onlinePayments' => $stripe->isConfigured(),
        ]);
    }

    public function generate(GenerateInvoicesRequest $request, GenerateTermInvoices $action): RedirectResponse
    {
        $created = $action->handle($request->term());

        return back()->with('status', $created === 0
            ? __('No new invoices: every student has one for this term, or no fee applies.')
            : trans_choice(':count invoice generated.|:count invoices generated.', $created));
    }
}
