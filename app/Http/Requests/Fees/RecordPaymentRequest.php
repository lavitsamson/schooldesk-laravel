<?php

declare(strict_types=1);

namespace App\Http\Requests\Fees;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
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
 * is checked by RecordPayment, with the invoice locked.
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
                    $fail('Enter an amount such as 150 or 150.50.');
                }
            }],
            'method' => ['required', Rule::in(array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::manual()))],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'reference' => ['nullable', 'string', 'max:100'],
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
