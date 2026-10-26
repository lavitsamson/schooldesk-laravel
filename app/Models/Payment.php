<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityArea;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\LogsSchoolActivity;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment towards an invoice, in minor units. Stripe payments are recorded by the webhook
 * (keyed by the payment intent, so a payment is never recorded twice); cash and bank transfers
 * by a school admin. A bank transfer's reference is unique per school (bank_reference), so
 * the same transfer is never recorded twice. A refund lowers refunded_amount instead of deleting
 * the payment.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int $amount
 * @property int $refunded_amount
 * @property PaymentMethod $method
 * @property string|null $stripe_payment_intent_id
 * @property string|null $reference
 * @property string|null $bank_reference
 * @property Carbon $paid_at
 * @property Carbon|null $refunded_at
 * @property int|null $recorded_by
 * @property string|null $receipt_path
 * @property-read Invoice $invoice
 * @property-read User|null $recorder
 */
#[Fillable(['school_id', 'invoice_id', 'amount', 'refunded_amount', 'method', 'stripe_payment_intent_id', 'reference', 'bank_reference', 'paid_at', 'refunded_at', 'recorded_by', 'receipt_path'])]
class Payment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use LogsSchoolActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'refunded_amount' => 'integer',
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function activityArea(): ActivityArea
    {
        return ActivityArea::Payments;
    }

    /**
     * @return list<string>
     */
    public function loggedAttributes(): array
    {
        return ['amount', 'refunded_amount', 'method', 'reference', 'paid_at', 'refunded_at'];
    }

    public function activityStudentId(): int
    {
        if ($this->relationLoaded('invoice')) {
            return $this->invoice->student_id;
        }

        // The invoice of this very payment, so no school scope is needed (seeders have none).
        return (int) Invoice::query()->withoutGlobalScopes()->whereKey($this->invoice_id)->value('student_id');
    }

    /**
     * The key a bank transfer is unique by within a school: "sepa 123" and "SEPA123" are the
     * same transfer.
     */
    public static function normalizeBankReference(string $reference): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', $reference));
    }

    /**
     * What the payment counts towards the invoice after refunds, in minor units.
     */
    public function netAmount(): int
    {
        return $this->amount - $this->refunded_amount;
    }

    public function receiptPath(): string
    {
        return "schools/{$this->school_id}/receipts/{$this->id}.pdf";
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
