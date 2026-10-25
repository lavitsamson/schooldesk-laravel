<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Fees\ListFamilyInvoices;
use App\Actions\Fees\StartCheckout;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @group Invoices
 *
 * The invoices of all the parent's children. Amounts are integers in minor units (cents) of
 * `currency`.
 */
class InvoiceController extends Controller
{
    /**
     * List invoices
     *
     * Newest first; void invoices are left out.
     *
     * @response {"data": [{"id": 88, "number": "SD-2026-000088", "student": {"id": 12, "name": "Pia Parent"}, "class": null, "term": "Autumn term", "total": 145000, "paid": 50000, "balance": 95000, "currency": "EUR", "status": "partially_paid", "status_label": "Partially paid", "overdue": false, "due_on": "2026-08-31"}], "links": {"first": null, "last": null, "prev": null, "next": null}, "meta": {"path": "https://schooldesk.test/api/v1/invoices", "per_page": 25, "next_cursor": null, "prev_cursor": null}}
     */
    public function index(ListRequest $request, #[CurrentUser] User $user, ListFamilyInvoices $invoices): AnonymousResourceCollection
    {
        Gate::authorize('viewFamily', Invoice::class);

        return InvoiceResource::collection($invoices->query($user)->cursorPaginate($request->limit()));
    }

    /**
     * Pay online
     *
     * Starts a Stripe Checkout session for the open balance; open `url` in a browser. The
     * payment is recorded once Stripe confirms it, not when the browser returns.
     *
     * @urlParam invoice_id integer required The invoice's id. Example: 88
     *
     * @response 201 {"data": {"url": "https://checkout.stripe.com/c/pay/cs_test_…"}}
     * @response 422 {"message": "This invoice has nothing left to pay.", "code": "validation_failed", "errors": {"payment": ["This invoice has nothing left to pay."]}}
     */
    public function checkoutSession(Invoice $invoice, #[CurrentUser] User $user, StartCheckout $action): JsonResponse
    {
        Gate::authorize('pay', $invoice);

        return JsonResource::make(['url' => $action->handle($invoice, $user)])->response()->setStatusCode(201);
    }
}
