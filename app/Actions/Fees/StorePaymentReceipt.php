<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\CurrentSchool;
use App\Support\Money;
use App\Support\PdfLogo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a payment's receipt PDF and stores it on the private disk. Run by the queued
 * GeneratePaymentReceipt job; a download whose receipt is not there yet (job still queued or
 * failed) calls it directly, so a receipt is never missing. A receipt is a record of the payment
 * when it was made, so it is rendered once and kept.
 */
final class StorePaymentReceipt
{
    public function __construct(private readonly CurrentSchool $currentSchool) {}

    public function handle(Payment $payment): string
    {
        $school = $this->currentSchool->get();
        $payment->loadMissing(['invoice.term', 'invoice.student', 'recorder']);
        $locale = $school->locale->value;

        $pdf = Pdf::loadView('pdf.receipt', [
            'school' => $school,
            'logo' => PdfLogo::dataUri($school->logo_path, Invoice::DISK),
            'payment' => $payment,
            'invoice' => $payment->invoice,
            'amount' => (new Money($payment->amount, $school->currency))->format($locale),
        ])->setPaper('a5')->output();

        $path = $payment->receiptPath();
        Storage::disk(Invoice::DISK)->put($path, $pdf);
        $payment->forceFill(['receipt_path' => $path])->save();

        return $path;
    }
}
