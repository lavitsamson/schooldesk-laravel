<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use NumberFormatter;

/**
 * An amount of money in integer minor units (cents) of a currency. Never a float: amounts are
 * parsed from strings digit by digit and added as integers. Only format() converts, at the very
 * edge, because intl's formatter takes a number (exact for any amount below 2^53 minor units).
 */
final readonly class Money implements JsonSerializable
{
    /**
     * Currencies without minor units, as Stripe treats them.
     */
    private const ZERO_DECIMAL = ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    public string $currency;

    public function __construct(public int $minor, string $currency)
    {
        if (preg_match('/^[A-Za-z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("Not a currency code: [{$currency}].");
        }

        $this->currency = strtoupper($currency);
    }

    public static function zero(string $currency): self
    {
        return new self(0, $currency);
    }

    /**
     * Parses an amount typed by a person: "1200", "1200.5", "1.200,50" or "1,200.50". The last
     * separator followed by at most two digits is the decimal separator (none for zero-decimal
     * currencies); other dots, commas and spaces group thousands.
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $amount, string $currency): self
    {
        $decimals = self::decimalsOf($currency);
        $amount = trim(str_replace(["\u{00A0}", "\u{202F}", ' '], '', $amount));

        $pattern = $decimals === 0
            ? '/^(?<units>\d{1,3}(?:[.,]\d{3})*|\d+)$/'
            : '/^(?<units>\d{1,3}(?:[.,]\d{3})*|\d+)(?:(?<separator>[.,])(?<fraction>\d{1,'.$decimals.'}))?$/';

        // "1.234.5": the decimal separator cannot also group the thousands.
        if (preg_match($pattern, $amount, $match) !== 1
            || (($match['separator'] ?? '') !== '' && str_contains($match['units'], $match['separator']))) {
            throw new InvalidArgumentException("Not an amount: [{$amount}].");
        }

        $units = str_replace(['.', ','], '', $match['units']);
        $fraction = str_pad($match['fraction'] ?? '', $decimals, '0');

        if (strlen($units) > 12) {
            throw new InvalidArgumentException("Amount too large: [{$amount}].");
        }

        return new self((int) ($units.$fraction), $currency);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    /**
     * @param  iterable<self>  $amounts
     */
    public static function sum(iterable $amounts, string $currency): self
    {
        $total = self::zero($currency);

        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    public function decimals(): int
    {
        return self::decimalsOf($this->currency);
    }

    /**
     * The amount as a plain decimal string ("1200.50"), e.g. to prefill a form field.
     */
    public function toDecimal(): string
    {
        $decimals = $this->decimals();
        $digits = str_pad((string) abs($this->minor), $decimals + 1, '0', STR_PAD_LEFT);
        $sign = $this->minor < 0 ? '-' : '';

        return $decimals === 0
            ? $sign.$digits
            : $sign.substr($digits, 0, -$decimals).'.'.substr($digits, -$decimals);
    }

    /**
     * "€1,200.50" in English, "1.200,50 €" in German.
     */
    public function format(string $locale): string
    {
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return (string) $formatter->formatCurrency($this->minor / (10 ** $this->decimals()), $this->currency);
    }

    /**
     * @return array{amount: int, currency: string}
     */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->minor, 'currency' => $this->currency];
    }

    private static function decimalsOf(string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? 0 : 2;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Cannot combine {$this->currency} and {$other->currency}.");
        }
    }
}
