<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Fees\RecordPayment;
use App\Actions\Fees\StorePaymentReceipt;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\RecordPaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PaymentController extends Controller
{
    public function store(RecordPaymentRequest $request, Invoice $invoice, #[CurrentUser] User $user, RecordPayment $action): RedirectResponse
    {
        $action->handle(
            $invoice,
            $request->amount(),
            $request->paymentMethod(),
            $user,
            $request->paidAt(),
            $request->reference(),
        );

        return back()->with('status', "Payment recorded. Invoice {$invoice->number} is now {$invoice->status->label()}.");
    }

    /**
     * The receipt PDF, for whoever may see the invoice. Rendered here if the queued job has
     * not stored it yet. Signed links only.
     */
    public function receipt(Payment $payment, StorePaymentReceipt $store): HttpResponse
    {
        $payment->load('invoice');
        Gate::authorize('view', $payment->invoice);

        $disk = Storage::disk(Invoice::DISK);
        $path = $payment->receipt_path !== null && $disk->exists($payment->receipt_path)
            ? $payment->receipt_path
            : $store->handle($payment);

        return $disk->download($path, "receipt-{$payment->invoice->number}-{$payment->id}.pdf", [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
