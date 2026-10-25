<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancels an invoice nobody has paid anything on (e.g. a scholarship, or a student who left
 * before the term). Final: the student is not invoiced for that term again, and the number
 * stays used, so the number sequence has no gaps.
 */
final class VoidInvoice
{
    public function handle(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->status !== InvoiceStatus::Issued || $locked->payments()->exists()) {
                throw ValidationException::withMessages(['status' => __('Only invoices without payments can be voided.')]);
            }

            $locked->forceFill(['status' => InvoiceStatus::Void, 'voided_at' => now()])->save();
            $invoice->setRawAttributes($locked->getAttributes(), sync: true);

            return $invoice;
        });
    }
}
