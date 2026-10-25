<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Fees\ListInvoices;
use App\Actions\Fees\RenderInvoicePdf;
use App\Actions\Fees\StartCheckout;
use App\Actions\Fees\VoidInvoice;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\ListInvoicesRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\StripeService;
use App\Support\CurrentSchool;
use App\Support\TermChoice;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Invoices: the term's list for school admins, and one invoice for admins and the student's
 * guardians, with the PDF, payments and receipts, recording a payment, voiding, and paying
 * online through Stripe Checkout (ADR 0008).
 */
class InvoiceController extends Controller
{
    /**
     * Signed PDF and receipt links on the page stay valid this long.
     */
    private const LINK_MINUTES = 30;

    public function index(ListInvoicesRequest $request, ListInvoices $action): Response
    {
        $year = $request->year();
        $term = $request->term();
        $filters = $request->filters($term);

        return Inertia::render('Invoices/Index', [
            'year' => $year->name,
            'terms' => TermChoice::options($year),
            'term' => ['id' => $term->id, 'name' => $term->name],
            'invoices' => InvoiceResource::collection($action->handle($filters)),
            'filters' => [...$filters, 'term' => $term->id],
            'classes' => $year->classes()->orderBy('name')->orderBy('section')->get()
                ->map(fn (SchoolClass $class): array => ['id' => $class->id, 'name' => $class->displayName()])
                ->values(),
            'statuses' => [
                ['value' => ListInvoicesRequest::ALL, 'label' => 'All'],
                ['value' => ListInvoicesRequest::OPEN, 'label' => 'Open'],
                ['value' => ListInvoicesRequest::OVERDUE, 'label' => 'Overdue'],
                ...array_map(fn (InvoiceStatus $s): array => ['value' => $s->value, 'label' => $s->label()], InvoiceStatus::cases()),
            ],
        ]);
    }

    public function show(Request $request, Invoice $invoice, #[CurrentUser] User $user, CurrentSchool $currentSchool, StripeService $stripe): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['student', 'term', 'schoolClass', 'items', 'payments' => fn ($q) => $q->with('recorder:id,name')->orderBy('paid_at')]);
        $today = $currentSchool->get()->today();
        $manages = $user->can('manage', Invoice::class);
        $expires = now()->addMinutes(self::LINK_MINUTES);

        return Inertia::render('Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'student' => ['id' => $invoice->student_id, 'name' => $invoice->student->fullName()],
                'class' => $invoice->schoolClass?->displayName(),
                'term' => ['id' => $invoice->term_id, 'name' => $invoice->term->name],
                'total' => $invoice->total,
                'paid' => $invoice->paidMoney()->minor,
                'balance' => $invoice->balanceMoney()->minor,
                // Prefills "record a payment": "150.00".
                'balance_input' => $invoice->balanceMoney()->toDecimal(),
                'due_on' => $invoice->due_on->toDateString(),
                'issued_at' => $invoice->issued_at->toIso8601String(),
                'voided_at' => $invoice->voided_at?->toIso8601String(),
                'overdue' => $invoice->isOverdueOn($today),
                'reminders_sent' => $invoice->reminders_sent,
                'pdf_url' => URL::temporarySignedRoute('invoices.pdf', $expires, ['invoice' => $invoice]),
                'items' => $invoice->items->map(fn (InvoiceItem $item): array => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'amount' => $item->amount,
                ])->values(),
                'payments' => $invoice->payments->map(fn (Payment $payment): array => [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'refunded_amount' => $payment->refunded_amount,
                    'method' => $payment->method->value,
                    'method_label' => $payment->method->label(),
                    // The office's notes are for staff only.
                    'reference' => $manages ? $payment->reference : null,
                    'recorded_by' => $manages ? $payment->recorder?->name : null,
                    'paid_at' => $payment->paid_at->toIso8601String(),
                    'receipt_url' => URL::temporarySignedRoute('payments.receipt', $expires, ['payment' => $payment]),
                ])->values(),
            ],
            'can' => [
                'manage' => $manages,
                'recordPayment' => $user->can('recordPayment', $invoice),
                'void' => $user->can('void', $invoice) && $invoice->payments->isEmpty(),
                'pay' => $stripe->isConfigured() && $user->can('pay', $invoice),
            ],
            'methods' => array_map(fn (PaymentMethod $m): array => ['value' => $m->value, 'label' => $m->label()], PaymentMethod::manual()),
            'today' => $today->toDateString(),
            'checkout' => in_array($request->query('checkout'), ['success', 'cancelled'], true) ? $request->query('checkout') : null,
        ]);
    }

    /**
     * Signed links only (from the invoice page), and authorised again here.
     */
    public function pdf(Invoice $invoice, RenderInvoicePdf $render): HttpResponse
    {
        Gate::authorize('view', $invoice);

        return response($render->handle($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-'.$invoice->number.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function void(Invoice $invoice, VoidInvoice $action): RedirectResponse
    {
        Gate::authorize('void', $invoice);
        $action->handle($invoice);

        return back()->with('status', "Invoice {$invoice->number} voided.");
    }

    /**
     * Sends the parent to Stripe Checkout (a full-page visit away from the app).
     */
    public function checkout(Invoice $invoice, #[CurrentUser] User $user, StartCheckout $action): HttpResponse
    {
        Gate::authorize('pay', $invoice);

        return Inertia::location($action->handle($invoice, $user));
    }
}
