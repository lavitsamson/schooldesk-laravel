<?php

declare(strict_types=1);

namespace App\Http\Requests\Fees;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\CurrentSchool;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * A cash or bank payment recorded by a school admin. Whether the amount fits the open balance
 * is checked by RecordPayment, with the invoice locked; it also turns a duplicate bank reference
 * entered at the same moment by someone else into the same validation error.
 */
class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('recordPayment', $this->invoice()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = app(CurrentSchool::class)->get()->today()->toDateString();

        return [
            'amount' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->parseAmount((string) $value)?->isPositive() !== true) {
                    $fail(__('Enter an amount such as 150 or 150.50.'));
                }
            }],
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::manual()))],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            // The bank's reference for a transfer (required, unique per school); a note for cash.
            'reference' => ['nullable', 'string', 'max:100', 'required_if:method,'.PaymentMethod::BankTransfer->value, function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->input('method') !== PaymentMethod::BankTransfer->value || ! is_string($value)) {
                    return;
                }

                $existing = Payment::query()
                    ->with('invoice:id,number')
                    ->where('bank_reference', Payment::normalizeBankReference($value))
                    ->first();

                if ($existing !== null) {
                    $fail(__('This bank transfer is already recorded, on invoice :number.', ['number' => $existing->invoice->number]));
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference.required_if' => __('Enter the bank reference of the transfer.'),
        ];
    }

    public function invoice(): Invoice
    {
        /** @var Invoice */
        return $this->route('invoice');
    }

    public function amount(): Money
    {
        return $this->parseAmount($this->string('amount')->toString()) ?? throw new InvalidArgumentException('Validated amount expected.');
    }

    public function paymentMethod(): PaymentMethod
    {
        return $this->enum('method', PaymentMethod::class) ?? PaymentMethod::Cash;
    }

    /**
     * Payments made today get the current time; earlier ones noon of that day (school timezone).
     */
    public function paidAt(): CarbonImmutable
    {
        $school = app(CurrentSchool::class)->get();
        $day = $this->string('paid_on')->toString();

        return $day === $school->today()->toDateString()
            ? CarbonImmutable::now()
            : CarbonImmutable::parse("{$day} 12:00", $school->timezone)->utc();
    }

    public function reference(): ?string
    {
        $reference = trim($this->string('reference')->toString());

        return $reference === '' ? null : $reference;
    }

    private function parseAmount(string $value): ?Money
    {
        try {
            return Money::parse($value, app(CurrentSchool::class)->get()->currency);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
