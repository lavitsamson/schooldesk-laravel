<?php

declare(strict_types=1);

namespace App\Support\Grading;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic on strings (bcmath) for marks, weights and percentages, which
 * come from decimal columns as strings. Throws on anything that is not a number, so a bad
 * value fails loudly instead of being read as 0.
 */
final class Decimal
{
    public const SCALE = 12;

    public static function add(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcadd(self::number($a), self::number($b), $scale);
    }

    public static function sub(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcsub(self::number($a), self::number($b), $scale);
    }

    public static function mul(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcmul(self::number($a), self::number($b), $scale);
    }

    public static function div(string $a, string $b, int $scale = self::SCALE): string
    {
        return bcdiv(self::number($a), self::number($b), $scale);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp(self::number($a), self::number($b), self::SCALE);
    }

    /**
     * Half up to the given decimals, for values that are never negative (marks, percentages).
     */
    public static function round(string $value, int $decimals = 2): string
    {
        return bcadd(self::number($value), self::number('0.'.str_repeat('0', $decimals).'5'), $decimals);
    }

    /**
     * Normalises a number to a fixed number of decimals without rounding ("17.5" → "17.50").
     */
    public static function fixed(string|int|float $value, int $decimals = 2): string
    {
        return bcadd(self::number((string) $value), '0', $decimals);
    }

    /**
     * @return numeric-string
     */
    private static function number(string $value): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Not a number: [{$value}].");
        }

        return $value;
    }
}
