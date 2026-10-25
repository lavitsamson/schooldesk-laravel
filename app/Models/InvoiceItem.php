<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on an invoice. Has no school_id of its own: items are only reached through their
 * (school-scoped) invoice. Description and amount are copied from the fee structure, so the
 * invoice stays as issued when the fee structure changes.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $fee_structure_id
 * @property string $description
 * @property int $amount
 * @property-read Invoice $invoice
 */
#[Fillable(['invoice_id', 'fee_structure_id', 'description', 'amount'])]
class InvoiceItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
