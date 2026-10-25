<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Sets an invoice's status from its payments net of refunds. Call it with the invoice row
 * locked, in the transaction that changed the payments. A void invoice stays void.
 */
final class RecalculateInvoiceStatus
{
    public function handle(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Void) {
            return $invoice;
        }

        $paid = (int) $invoice->payments()->sum(DB::raw('amount - refunded_amount'));

        $invoice->status = InvoiceStatus::forAmounts($invoice->total, $paid);
        $invoice->save();

        return $invoice;
    }
}
