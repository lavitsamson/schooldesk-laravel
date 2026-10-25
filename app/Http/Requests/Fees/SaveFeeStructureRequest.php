<?php

declare(strict_types=1);

namespace App\Http\Requests\Fees;

use App\Enums\FeeFrequency;
use App\Models\FeeStructure;
use App\Support\CurrentSchool;
use App\Support\Money;
use App\Support\SchoolRule;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * A fee structure. The amount is typed as a decimal ("1200", "1.200,50") and stored as an
 * integer in minor units; the class must belong to the chosen academic year.
 */
class SaveFeeStructureRequest extends FormRequest
{
    /**
     * Upper bound per fee: 1,000,000.00 in a two-decimal currency.
     */
    private const MAX_MINOR = 100_000_000;

    public function authorize(): bool
    {
        $feeStructure = $this->feeStructure();

        return $this->user()?->can($feeStructure === null ? 'create' : 'update', $feeStructure ?? FeeStructure::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', SchoolRule::exists('academic_years')],
            'class_id' => ['nullable', 'integer', SchoolRule::exists('classes')->where('academic_year_id', $this->integer('academic_year_id'))],
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                $money = $this->parseAmount((string) $value);

                if ($money === null) {
                    $fail('Enter an amount such as 1200 or 1200.50.');
                } elseif (! $money->isPositive() || $money->minor > self::MAX_MINOR) {
                    $fail('Enter an amount above zero and at most 1,000,000.');
                }
            }],
            'frequency' => ['required', Rule::enum(FeeFrequency::class)],
            'due_day_offset' => ['required', 'integer', 'min:0', 'max:180'],
        ];
    }

    /**
     * @return array{academic_year_id: int, class_id: int|null, name: string, amount: int, frequency: FeeFrequency, due_day_offset: int}
     */
    public function feeData(): array
    {
        return [
            'academic_year_id' => $this->integer('academic_year_id'),
            'class_id' => $this->filled('class_id') ? $this->integer('class_id') : null,
            'name' => $this->string('name')->toString(),
            'amount' => (int) $this->parseAmount($this->string('amount')->toString())?->minor,
            'frequency' => $this->enum('frequency', FeeFrequency::class) ?? FeeFrequency::Term,
            'due_day_offset' => $this->integer('due_day_offset'),
        ];
    }

    public function feeStructure(): ?FeeStructure
    {
        /** @var FeeStructure|null */
        return $this->route('feeStructure');
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
