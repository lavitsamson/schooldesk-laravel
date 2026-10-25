<?php

declare(strict_types=1);

namespace App\Actions\Fees;

use App\Enums\PaymentMethod;
use App\Jobs\GeneratePaymentReceipt;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\CurrentSchool;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A cash or bank payment recorded by a school admin. Partial payments are fine; paying more
 * than the open balance, or paying a paid or void invoice, is not. The invoice row is locked,
 * so two admins recording at once cannot overpay it. The receipt PDF is rendered by a queued
 * job once the payment is committed.
 */
final class RecordPayment
{
    public function __construct(
        private readonly CurrentSchool $currentSchool,
        private readonly RecalculateInvoiceStatus $recalculate,
    ) {}

    public function handle(
        Invoice $invoice,
        Money $amount,
        PaymentMethod $method,
        User $recordedBy,
        CarbonInterface $paidAt,
        ?string $reference = null,
    ): Payment {
        if ($method === PaymentMethod::Stripe) {
            throw ValidationException::withMessages(['method' => 'Card payments are recorded by Stripe.']);
        }

        if (! $amount->isPositive()) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount above zero.']);
        }

        $payment = DB::transaction(function () use ($invoice, $amount, $method, $recordedBy, $paidAt, $reference): Payment {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $locked->status->isOpen()) {
                throw ValidationException::withMessages(['amount' => "This invoice is {$locked->status->label()}: there is nothing to pay."]);
            }

            $balance = $locked->balanceMoney();

            if ($amount->greaterThan($balance)) {
                $open = $balance->format($this->currentSchool->get()->locale->value);

                throw ValidationException::withMessages(['amount' => "The open balance is {$open}. Record at most that."]);
            }

            $payment = $locked->payments()->create([
                'amount' => $amount->minor,
                'method' => $method,
                'reference' => $reference,
                'paid_at' => $paidAt,
                'recorded_by' => $recordedBy->id,
            ]);

            $this->recalculate->handle($locked);
            $invoice->setRawAttributes($locked->getAttributes(), sync: true);

            return $payment;
        });

        GeneratePaymentReceipt::dispatch($payment->school_id, $payment->id);

        return $payment;
    }
}
