<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Fees\StorePaymentReceipt;
use App\Actions\Guardians\NotifyPrimaryGuardian;
use App\Models\Payment;
use App\Notifications\PaymentReceived;
use App\Support\CurrentSchool;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Renders and stores the receipt of a new payment, then tells the primary guardian it was
 * received. The receipt may exist already (downloaded before the job ran): then it is kept.
 */
final class GeneratePaymentReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $schoolId,
        public readonly int $paymentId,
    ) {}

    public function handle(CurrentSchool $currentSchool, StorePaymentReceipt $store, NotifyPrimaryGuardian $notify): void
    {
        $currentSchool->runInSchoolLocale($this->schoolId, function () use ($store, $notify): void {
            $payment = Payment::query()->with(['invoice.school', 'invoice.term', 'invoice.student'])->find($this->paymentId);

            if ($payment === null) {
                return;
            }

            if ($payment->receipt_path === null) {
                $store->handle($payment);
            }

            $notify->handle($payment->invoice->student, new PaymentReceived($payment));
        });
    }
}
