<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The next invoice number of a school and year, e.g. SD-2026-000123. Sequential without gaps
 * (as German bookkeeping rules expect): the counter row is locked and incremented inside the
 * caller's transaction, so a rolled-back invoice gives its number back.
 *
 * The sequence table is read with the query builder, so the school id is passed explicitly.
 */
final class NextInvoiceNumber
{
    public function handle(int $schoolId, int $year): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Invoice numbers must be taken inside the transaction that creates the invoice.');
        }

        // Created once per school and year; insertOrIgnore copes with two first invoices at once.
        DB::table('invoice_number_sequences')->insertOrIgnore([
            'school_id' => $schoolId,
            'year' => $year,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = DB::table('invoice_number_sequences')
            ->where('school_id', $schoolId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first(['id', 'last_number']);

        $next = (int) $sequence?->last_number + 1;

        DB::table('invoice_number_sequences')
            ->where('id', $sequence?->id)
            ->update(['last_number' => $next, 'updated_at' => now()]);

        return sprintf('%s-%d-%06d', config('schooldesk.fees.invoice_prefix'), $year, $next);
    }
}
